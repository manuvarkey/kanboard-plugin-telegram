<?php

namespace Kanboard\Plugin\Telegram\Notification;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramClass;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboard;

use Kanboard\Core\Base;
use Kanboard\Core\Notification\NotificationInterface;
use Kanboard\Model\TaskModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\CommentModel;
use Kanboard\Model\TaskFileModel;

/**
 * Telegram Notification
 *
 * @package  notification
 * @author   Manu Varkey
 */

// Helper functions

function tempnam_sfx($path, $suffix)
{
    do
    {
        $file = $path."/".mt_rand().$suffix;
        $fp = @fopen($file, 'x');
    }
    while(!$fp);

    fclose($fp);
    return $file;
}

function clean($string) 
{
    $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
    return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

// Overloaded classes 

class Telegram extends Base implements NotificationInterface
{
    const SUBTASK_CLOSE = "subtask_close";
    const SUBTASK_INPROGRESS = "subtask_inprogress";
    const SUBTASK_INPROGRESS_WITH_TIMER = "subtask_inprogress_timer";
    const SUBTASK_START_TIMER = "subtask_start_timer";
    const SUBTASK_STOP_TIMER = "subtask_stop_timer";
    const TASK_COMMENT = "comment";
    /**
     * Send notification to a user
     *
     * @access public
     * @param  array     $user
     * @param  string    $eventName
     * @param  array     $eventData
     */
    public function notifyUser(array $user, $eventName, array $eventData)
    {
        $apikey = $this->userMetadataModel->get($user['id'], 'telegram_apikey', $this->configModel->get('telegram_apikey'));
        $bot_username = $this->userMetadataModel->get($user['id'], 'telegram_username', $this->configModel->get('telegram_username'));
        $chat_id = $this->userMetadataModel->get($user['id'], 'telegram_user_cid');
        if (! empty($apikey)) 
        {
            if ($eventName === TaskModel::EVENT_OVERDUE) 
            {
                foreach ($eventData['tasks'] as $task) 
                {
                    $project = $this->projectModel->getById($task['project_id']);
                    $eventData['task'] = $task;
                    $this->sendMessage($apikey, $bot_username, $chat_id, $project, $eventName, $eventData);
                }
            } 
            else 
            {
                $project = $this->projectModel->getById($eventData['task']['project_id']);
                $this->sendMessage($apikey, $bot_username, $chat_id, $project, $eventName, $eventData);
            }
        }
    }
    
    /**
     * Send notification to a project
     *
     * @access public
     * @param  array     $project
     * @param  string    $eventName
     * @param  array     $eventData
     */
    public function notifyProject(array $project, $eventName, array $eventData)
    {
        $apikey = $this->projectMetadataModel->get($project['id'], 'telegram_apikey', $this->configModel->get('telegram_apikey'));
        $bot_username = $this->projectMetadataModel->get($project['id'], 'telegram_username', $this->configModel->get('telegram_username'));
        $chat_id = $this->projectMetadataModel->get($project['id'], 'telegram_group_cid');
        if (! empty($apikey)) 
        {
            $this->sendMessage($apikey, $bot_username, $chat_id, $project, $eventName, $eventData);
        }
    }
    
    /**
     * Send message to Telegram
     *
     * @access protected
     * @param  string    $apikey
     * @param  string    $bot_username
     * @param  string    $chat_id
     * @param  array     $project
     * @param  string    $eventName
     * @param  array     $eventData
     */
    protected function sendMessage($apikey, $bot_username, $chat_id, array $project, $eventName, array $eventData)
    {
    
        // Get required data
        $keyboard_buttons = array();
        
        if ($this->userSession->isLogged()) 
        {
            $author = $this->helper->user->getFullname();
            $title = $this->notificationModel->getTitleWithAuthor($author, $eventName, $eventData);
        }
        else 
        {
            $title = $this->notificationModel->getTitleWithoutAuthor($eventName, $eventData);
        }
        
        $proj_name = isset($eventData['project_name']) ? $eventData['project_name'] : $eventData['task']['project_name'];
        $task_title = $eventData['task']['title'];
        $task_url = $this->helper->url->to('TaskViewController', 'show', array('task_id' => $eventData['task']['id'], 'project_id' => $project['id']), '', true);
        
        $attachment = '';
        
        // Build message
        
        $message = "[".htmlspecialchars($proj_name, ENT_NOQUOTES | ENT_IGNORE)."]\n";
        $message .= htmlspecialchars($title, ENT_NOQUOTES | ENT_IGNORE)."\n";
        
        if ($this->configModel->get('application_url') !== '') 
        {
            $message .= '📝 <a href="'.$task_url.'">'.htmlspecialchars($task_title, ENT_NOQUOTES | ENT_IGNORE).'</a>';
        }
        else
        {
            $message .= htmlspecialchars($task_title, ENT_NOQUOTES | ENT_IGNORE);
        }
        
        // Add additional informations
        
        $description_events = array(TaskModel::EVENT_CREATE, TaskModel::EVENT_UPDATE, TaskModel::EVENT_USER_MENTION);
        $subtask_events = array(SubtaskModel::EVENT_CREATE, SubtaskModel::EVENT_UPDATE, SubtaskModel::EVENT_DELETE);
        $comment_events = array(CommentModel::EVENT_UPDATE, CommentModel::EVENT_CREATE, CommentModel::EVENT_DELETE, CommentModel::EVENT_USER_MENTION);
        
        if (in_array($eventName, $subtask_events))  // For subtask events
        {
            $subtask_status = $eventData['subtask']['status'];
            $subtask_symbol = '';
            
            if ($subtask_status == SubtaskModel::STATUS_DONE)
            {
                $subtask_symbol = '[X] ';
            }
            elseif ($subtask_status == SubtaskModel::STATUS_TODO)
            {
                $subtask_symbol = '[ ] ';
                $keyboard_buttons[] =new InlineKeyboardButton([
                  'text'          => t("Work on SubTask"),
                  'callback_data' => self::SUBTASK_INPROGRESS."/".$eventData['subtask']['id'],
                ]);
                if( ! $this->subtaskTimeTrackingModel->hasTimer($eventData['subtask']['id'], $eventData['subtask']['user_id'])){
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("Work on SubTask timer"),
                    'callback_data' => self::SUBTASK_INPROGRESS_WITH_TIMER."/".$eventData['subtask']['id'],
                  ]);
                }else{
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("stop"),
                    'callback_data' => self::SUBTASK_STOP_TIMER."/".$eventData['subtask']['id'],
                  ]);
                }
            }
            elseif ($subtask_status == SubtaskModel::STATUS_INPROGRESS)
            {
                $subtask_symbol = '[~] ';
                $keyboard_buttons[] =new InlineKeyboardButton([
                  'text'          => t("Close SubTask"),
                  'callback_data' => self::SUBTASK_CLOSE."/".$eventData['subtask']['id'],
                ]);
                
                if( ! $this->subtaskTimeTrackingModel->hasTimer($eventData['subtask']['id'], $eventData['subtask']['user_id'])){
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("start"),
                    'callback_data' => self::SUBTASK_START_TIMER."/".$eventData['subtask']['id'],
                  ]);
                }else{
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("stop"),
                    'callback_data' => self::SUBTASK_STOP_TIMER."/".$eventData['subtask']['id'],
                  ]);
                }
            }
            
            $message .= "\n<b>  ↳ ".$subtask_symbol.'</b> <em>"'.htmlspecialchars($eventData['subtask']['title'], ENT_NOQUOTES | ENT_IGNORE).'"</em>';
        }
        
        elseif (in_array($eventName, $description_events))  // If description available
        {
            if ($eventData['task']['description'] != '')
            {
                $message .= "\n✏️ ".'<em>"'.htmlspecialchars($eventData['task']['description'], ENT_NOQUOTES | ENT_IGNORE).'"</em>';
            }
        }
        
        elseif (in_array($eventName, $comment_events))  // If comment available
        {
			$message = self::TASK_COMMENT."/".$eventData['task']['id']."\n".$message;
            $message .= "\n💬 ".'<em>"'.htmlspecialchars($eventData['comment']['comment'], ENT_NOQUOTES | ENT_IGNORE).'"</em>';
        }
        
        elseif ($eventName === TaskFileModel::EVENT_CREATE)  // If attachment available
        {
            $file_path = getcwd()."/data/files/".$eventData['file']['path'];
            $file_name = $eventData['file']['name'];
            $is_image = $eventData['file']['is_image'];
            
            $attachment = tempnam_sfx(sys_get_temp_dir(), clean($file_name));
            file_put_contents($attachment, file_get_contents($file_path));
        }
        
        // Send Message
        
        try
        {   
            
            // Create Telegram API object
            $telegram = new TelegramClass($apikey, $bot_username);

            // Message pay load
            $replyMarkup = new InlineKeyboard($keyboard_buttons);
            $data = array('chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'HTML','reply_markup' => $replyMarkup);

            $result = Request::sendMessage($data);

            // Send any attachment if exists
            if ($attachment != '')
            {
                if ($is_image == true)
                {
                    // Sent image
                    $data_file = ['chat_id' => $chat_id, 
                                  'photo'   => Request::encodeFile($attachment),
                                  'caption' => '📎 '.$file_name,
                                 ];
                    $result_att = Request::sendPhoto($data_file);
                }
                else
                {
                    // Sent attachment
                    $data_file = ['chat_id'  => $chat_id, 
                                  'document' => Request::encodeFile($attachment),
                                  'caption' => '📎 '.$file_name,
                                 ];
                    $result_att = Request::sendDocument($data_file);
                }
                
                // Remove temporory file
                unlink($attachment);
            }
        } 
        catch (TelegramException $e) 
        {
            // log telegram errors
            error_log($e->getMessage());
        }
    }
}

