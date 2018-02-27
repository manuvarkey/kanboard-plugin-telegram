<?php

namespace Kanboard\Plugin\Telegram;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
//use Kanboard\Console;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Kanboard\Console\BaseCommand;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramClass;
use Longman\TelegramBot\Exception\TelegramException;

/**
 * Class TelegramCommand
 *
 * @package Kanboard\Console
 * @author  Frederic Guillot
 */
class TelegramCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('plugin:telegram:version')
            ->setDescription('Display Kanboard version')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("hi from telegram!");

        $apikey = $this->configModel->get('telegram_apikey');
        $bot_username = $this->configModel->get('telegram_username');
        $offset = 0+$this->configModel->get('telegram_offset');
        
        list($offset, $chat_id, $user_name) = $this->process_commands($apikey, $bot_username, $offset,$output);
        
        if($offset != 0){
          //ok
          $this->configModel->save( array('telegram_offset' => $offset) );
        }else{
          //error
        }
    }

    private function process_commands($apikey, $bot_username, $offset,OutputInterface $output)
    {
      try
      {

        // Create Telegram API object
        $telegram = new TelegramClass($apikey, $bot_username);

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
        $user_name="";

        if ($response->isOk()) {
          //Process all updates
          /** @var Update $result */
          foreach ((array) $response->getResult() as $result) {
            $offset = $result->getUpdateId();
            if( $result->getMessage() != NULL){
              $output->writeln("new message '".trim($result->getMessage()->getText())."' from '".$result->getMessage()->getChat()->getId()."'");
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

      return array($offset, $chat_id, $user_name);
    }
}
