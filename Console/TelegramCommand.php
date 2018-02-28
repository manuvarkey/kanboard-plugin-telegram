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
              
              if( $result->getMessage() != NULL){
                $chat_id = $result->getMessage()->getChat()->getId();
                $message = trim($result->getMessage()->getText());
                $output->writeln("new message '$message' from '$chat_id'");
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
