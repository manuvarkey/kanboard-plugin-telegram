<?php

namespace Kanboard\Plugin\Telegram;

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

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramClass;
use Longman\TelegramBot\Exception\TelegramException;

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
              
          //ok
          
          foreach($this->getAllUsersByTelegramChatId($chat_id) as $user){
            $this->output->writeln("user_id=".$user['id']." ");
            $tcmd = explode('/',$data);
            if(count($tcmd)>2){
              switch($tcmd[0]){
                case 'close':
                  $this->output->writeln("got close subtask=".$tcmd[3]." ");
                  $this->subtaskModel->update(array('id'=>$tcmd[3],'status'=>SubtaskModel::STATUS_DONE));
                  break;
                case 'work':
                  $this->output->writeln("got work subtask=".$tcmd[3]." ");
                  $this->subtaskModel->update(array('id'=>$tcmd[3],'status'=>SubtaskModel::STATUS_INPROGRESS));
                  break;
              }
            }
          }
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
                $chat_id        = $q->getFrom()->getId();
                $query_id       = $q->getId();

                $message = trim($q->getText());
                $output->writeln("new getCallbackQuery '$message' from '$chat_id'".print_r($data,true));

                $this->processCallbackQuery($chat_id,$message,$data);
                
                Request::answerCallbackQuery(['callback_query_id' => $query_id]);
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
