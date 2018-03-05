<?php

namespace Kanboard\Plugin\Telegram\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
//use Kanboard\Console;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;

use Kanboard\Core\Base;
use Kanboard\Console\BaseCommand;
use Kanboard\Model\UserMetadataModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\SubtaskTimeTrackingModel;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramClass;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboard;

use Kanboard\Plugin\Telegram\Notification\Telegram;

/*class UserMetadataModelTelegram extends UserMetadataModel
{

}*/

/**
 * Class TelegramCommand
 *
 */
class TelegramCommand extends BaseCommand
{
    protected $output;
    
    protected function configure()
    {
        $this
            ->setName('plugin:telegram:pool')
            ->setDescription('Display Kanboard version')
            ->addArgument('daemon', InputArgument::OPTIONAL, 'Run as daemon', false);
        ;
    }

    private function processCallbackQuery($chat_id, $message, $data){

        $keyboard_buttons = array();
        
          foreach($this->getAllUsersByTelegramChatId($chat_id) as $user){
            $this->output->writeln("user_id=".$user['id']." ".$data);
            list($tcmd,$arg) = explode('/',$data);
            
            switch($tcmd){
              case Telegram::SUBTASK_CLOSE:
                $subtask = $this->subtaskModel->getById($arg);
                $this->output->writeln("got close subtask=");//.print_r($subtask,true)." ");
                if($subtask['user_id'] == 0  || $subtask['user_id'] == $user['id']){
                  //~ $this->subtaskModel->update(array('id'=>$arg,'status'=>SubtaskModel::STATUS_DONE));
                  $status = $this->subtaskStatusModel->toggleStatus($subtask['id']);
                  $this->subtaskTimeTrackingModel->toggleTimer($subtask['id'], $user['id'],$status);
                  $this->subtaskTimeTrackingModel->updateTaskTimeTracking($subtask['task_id']);
                }
                break;
              case Telegram::SUBTASK_INPROGRESS:
              case Telegram::SUBTASK_INPROGRESS_WITH_TIMER:
                $subtask = $this->subtaskModel->getById($arg);
                $this->output->writeln("got INPROGRESS subtask=");//.print_r($subtask,true));
                if($subtask['user_id'] == 0 || $subtask['user_id'] == $user['id']){
                  //~ $this->subtaskModel->update(array('id'=>$subtask['id'],'status'=>SubtaskModel::STATUS_INPROGRESS,'user_id'=>$user['id']));
                  if($tcmd == Telegram::SUBTASK_INPROGRESS_WITH_TIMER){
                      //~ $this->subtaskTimeTrackingModel->logStartTime($subtask['id'], $user['id']);
                      $status = ($subtask['status'] + 1) % 3;//from app/Model/SubtaskStatusModel.php
                      $this->subtaskTimeTrackingModel->toggleTimer($subtask['id'], $user['id'],$status);
                  }
                  $status = $this->subtaskStatusModel->toggleStatus($subtask['id']);
                }
                break;
              case Telegram::SUBTASK_START_TIMER:
                $subtask = $this->subtaskModel->getById($arg);
                $this->output->writeln("got SUBTASK_START_TIMER subtask=");//.print_r($subtask,true));
                if($subtask['user_id'] == 0 || $subtask['user_id'] == $user['id']){
                  $this->subtaskTimeTrackingModel->logStartTime($subtask['id'], $user['id']);

                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("Close SubTask"),
                    'callback_data' => Telegram::SUBTASK_CLOSE."/".$subtask['id'],
                  ]);
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("stop"),
                    'callback_data' => Telegram::SUBTASK_STOP_TIMER."/".$subtask['id'],
                  ]);
                }
                break;
              case Telegram::SUBTASK_STOP_TIMER:
                $subtask = $this->subtaskModel->getById($arg);
                $this->output->writeln("got SUBTASK_STOP_TIMER subtask=");//.print_r($subtask,true));
                if($subtask['user_id'] == 0 || $subtask['user_id'] == $user['id']){
                  $this->subtaskTimeTrackingModel->logEndTime($subtask['id'], $user['id']);
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("Close SubTask"),
                    'callback_data' => Telegram::SUBTASK_CLOSE."/".$subtask['id'],
                  ]);
                  $keyboard_buttons[] =new InlineKeyboardButton([
                    'text'          => t("start"),
                    'callback_data' => Telegram::SUBTASK_START_TIMER."/".$subtask['id'],
                  ]);
                }
                break;
              case Telegram::TASK_COMMENT:
                $task = $this->taskFinderModel->getById($arg);
                $this->commentModel->create(array(
                'comment' => $message,
                'user_id' => $user['id'],
                'task_id' => $task['id']
                ));
                break;
            }
          }
          if(count($keyboard_buttons)>0){
            return new InlineKeyboard($keyboard_buttons);
          }
          return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

      $this->output=$output;
      
      $apikey = $this->configModel->get('telegram_apikey');
      $bot_username = $this->configModel->get('telegram_username');
      // Create Telegram API object
      $telegram = new TelegramClass($apikey, $bot_username);

      do{
        $offset = 0+$this->configModel->get('telegram_offset');
        $output->writeln("hi from telegram! offset=$offset");


        try
        {


          $limit=100;
          $timeout = 1;
          $response  = Request::getUpdates(
            [
            'offset'  => $offset+1,
            'limit'   => $limit,
            'timeout' => $timeout,
            ]
            );

          $chat_id="";
          $message="";
          $data = "";

          if ($response->isOk()) {
            //Process all updates
            /** @var Update $result */
            //~ $output->writeln("new getCallbackQuery".print_r($response->getResult(),true));
            foreach ((array) $response->getResult() as $result) {
              $offset = $result->getUpdateId();
              $this->configModel->save( array('telegram_offset' => $offset) );
              //~ $output->writeln("new message ".print_r($result,true));
              
              $Message = $result->getMessage();
              if( !isset($Message) ){
                $Message = $result->getEditedMessage();
              }
              if( isset($Message) ){
                $chat_id = $Message->getChat()->getId();
                $from_id = $Message->getFrom()->getId();
                $message = trim($Message->getText());
                $reply2message = $Message->getReplyToMessage();
                $output->writeln("new message '$message' from '$from_id' in '$chat_id'");//.print_r($reply2message,true));

                if($reply2message == NULL){
                  $data = mb_strstr($message, '@'.$bot_username);
                  if($data !== false){
                    $data = trim(mb_substr($data,mb_strpos($data, ' ')));
                    if(mb_strpos($message, ' ')){
                      list($data,$message) = explode(' ',$data,2);
                    }else{
                      $data = $message;//assume command without arguments
                      $message="";
                    }
                  }elseif(mb_strpos($message, ' ')){
                    list($data,$message) = explode(' ',$message,2);
                  }else{
                    $data = $message;//assume command without arguments
                    $message="";
                  }
                }else{
                  $reptest = trim($reply2message->getText());
                  $data = strtok($reptest, "\r\n");
                }
                $output->writeln("data=$data message=$message");

                // Message pay load
                $replyMarkup = $this->processCallbackQuery($from_id,$message,$data);

                if($replyMarkup !== false){
                  $answer = array('chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'HTML');
                  $answer['reply_markup'] = $replyMarkup;
                  $result = Request::sendMessage($data);
                }

               }elseif($result->getCallbackQuery() != NULL){
                $q = $result->getCallbackQuery();
                $data = $q->getData();
                //$chat_id = $q->getId();
                $chat_id        = $q->getMessage()->getChat()->getId();
                $from_id        = $q->getFrom()->getId();
                $query_id       = $q->getId();
                $message_id     = $q->getMessage()->getMessageId();

                $message = trim($q->getText());
                $output->writeln("new getCallbackQuery '$message' from '$from_id' in '$chat_id'");//.print_r($q->getMessage()->getChat(),true));

                
                $replyMarkup = $this->processCallbackQuery($from_id,$message,$data);
                if($replyMarkup !== false){
                  $answer['chat_id'] = $chat_id;
                  $answer['message_id'] = $message_id;
                  $answer['reply_markup'] = $replyMarkup;
                  //$output->writeln("new answer ".print_r($answer,true));
                  Request::editMessageReplyMarkup($answer);
                }else{
                  $answer['callback_query_id'] = $query_id;
                  Request::answerCallbackQuery($answer);
                }
               }
            }
          }else{
            throw new TelegramException($response->printError(true));
          }
        }
        catch (TelegramException $e)
        {
          // log telegram errors
          error_log($e->getMessage());
          $this->flash->failure(t('Telegram error: ').$e->getMessage());
          return 0;//$this->response->redirect($this->helper->url->to('UserViewController', 'integrations', array('user_id' => $user['id'] )), true);
        }

        $this->memoryCache->flush();
      }while($input->getArgument('daemon')!==false);
    }

    public function getAllUsersByTelegramChatId($chat_id){
      $new_array = array();
      foreach($this->getAllUsersIdByTelegramChatId($chat_id) as $uid){
        $new_array[] = $this->userModel->getById($uid);
      }
      return $new_array;
    }

    public function getAllUsersIdByTelegramChatId($chat_id)
    {
        if (empty($chat_id)) {
            return array();
        }

        return $this->db
            ->table('user_has_metadata')
            ->eq('name', 'telegram_user_cid')
            ->eq('value', $chat_id)
            ->findAllByColumn('user_id')?:array();
    }

}
