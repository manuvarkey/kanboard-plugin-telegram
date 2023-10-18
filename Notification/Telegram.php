<?php

namespace Kanboard\Plugin\Telegram\Notification;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramClass;
use Longman\TelegramBot\Exception\TelegramException;
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

function clean($string) 
{
    $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
    return preg_replace('/[^A-Za-z0-9\-.]/', '', $string); // Removes special chars.
}

// Overloaded classes 

class Telegram extends Base implements NotificationInterface
{
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
        $forward_attachments = $this->userMetadataModel->get($user['id'], 'forward_attachments', $this->configModel->get('forward_attachments'));
        
        if (! empty($apikey)) 
        {
            if ($eventName === TaskModel::EVENT_OVERDUE) 
            {
                foreach ($eventData['tasks'] as $task) 
                {
                    $project = $this->projectModel->getById($task['project_id']);
                    $eventData['task'] = $task;
                    $this->sendMessage($apikey, $bot_username, $forward_attachments, $chat_id, $project, $eventName, $eventData);
                }
            } 
            else 
            {
                $project = $this->projectModel->getById($eventData['task']['project_id']);
                $this->sendMessage($apikey, $bot_username, $forward_attachments, $chat_id, $project, $eventName, $eventData);
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
        $forward_attachments = $this->userMetadataModel->get($project['id'], 'forward_attachments', $this->configModel->get('forward_attachments'));
        
        if (! empty($apikey)) 
        {
            $this->sendMessage($apikey, $bot_username, $forward_attachments, $chat_id, $project, $eventName, $eventData);
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
    protected function sendMessage($apikey, $bot_username, $forward_attachments, $chat_id, array $project, $eventName, array $eventData)
    {
    
        // Get required data
        
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
                $subtask_symbol = '❌ ';
            }
            elseif ($subtask_status == SubtaskModel::STATUS_TODO)
            {
                $subtask_symbol = '';
            }
            elseif ($subtask_status == SubtaskModel::STATUS_INPROGRESS)
            {
                $subtask_symbol = '🕘 ';
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
            $message .= "\n💬 ".'<em>"'.htmlspecialchars($eventData['comment']['comment'], ENT_NOQUOTES | ENT_IGNORE).'"</em>';
        }
        
        elseif ($eventName === TaskFileModel::EVENT_CREATE and $forward_attachments)  // If attachment available
        {
            $file_path = getcwd()."/data/files/".$eventData['file']['path'];
            $file_name = $eventData['file']['name'];
            $is_image = $eventData['file']['is_image'];
            
            mkdir(sys_get_temp_dir()."/kanboard_telegram_plugin");
            $attachment = sys_get_temp_dir()."/kanboard_telegram_plugin/".clean($file_name);
            file_put_contents($attachment, file_get_contents($file_path));
        }
        
        // Send Message
        
        try
        {   
            
            // Create Telegram API object
            $telegram = new TelegramClass($apikey, $bot_username);
            
            // Setup proxy details if set in kanboard configuration
            if (HTTP_PROXY_HOSTNAME != '')
	    {
		    if (HTTP_PROXY_USERNAME != '')
		    {
			    $proxy_name = 'tcp://'.HTTP_PROXY_USERNAME.':'.HTTP_PROXY_PASSWORD.'@'.HTTP_PROXY_HOSTNAME.':'.HTTP_PROXY_PORT;
		    }
		    else
		    {
			    $proxy_name = 'tcp://'.HTTP_PROXY_HOSTNAME.':'.HTTP_PROXY_PORT;
		    }
                    Request::setClient(new \GuzzleHttp\Client([
			    'base_uri' => 'https://api.telegram.org',
                            'timeout' => 10,
                            'request.options' => ['proxy'   => $proxy_name],
                    ]));
            }

            // Message pay load
            $data = array('chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'HTML');
            
            // Send message
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
                rmdir(sys_get_temp_dir()."/kanboard_telegram_plugin");
            }
        }
        catch (TelegramException $e)
        {
            // log telegram errors
            error_log($e->getMessage());
        }
    }
}
