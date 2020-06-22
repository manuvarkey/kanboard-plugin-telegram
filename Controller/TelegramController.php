<?php
namespace Kanboard\Plugin\Telegram\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramClass;
use Longman\TelegramBot\Exception\TelegramException;
use Kanboard\Core\Base;

class TelegramController extends BaseController
{
    public function get_user_chat_id()
    {
        $user = $this->getUser();
        //$this->checkCSRFParam();
        $apikey = $this->userMetadataModel->get($user['id'], 'telegram_apikey', $this->configModel->get('telegram_apikey'));
        $bot_username = $this->userMetadataModel->get($user['id'], 'telegram_username', $this->configModel->get('telegram_username'));
        try {
                $offset = 0 + (int)$this->userMetadataModel->get($user['id'], 'telegram_offset', $this->configModel->get('telegram_offset'));
        } catch (Exception $e) {
                $offset=0;
        }  
        
        // Preventing "A non-numeric value encountered in /var/www/app/plugins/Telegram/Controller/TelegramController.php"

        $offset = 0 + $this->userMetadataModel->get($user['id'], 'telegram_offset', $this->configModel->get('telegram_offset'));
        $private_message = mb_substr(urldecode($this->request->getStringParam('private_message')), 0, 32);

        list($offset, $chat_id, $user_name) = $this->get_chat_id($apikey, $bot_username, $offset, $private_message);

        if ($offset != 0)
        {
            //ok
            $this->userMetadataModel->save($user['id'], array('telegram_offset' => $offset));
            $this->response->html($this->template->render('telegram:user/save_chat_id', array('chat_id' => $chat_id, 'user_name' => $user_name, 'private_message' => $private_message, 'bot_url' => "https://t.me/" . $bot_username, 'user' => $user)));
        }
        else
        {
            //error
            $this->response->redirect($this->helper->url->to('UserViewController', 'integrations', array('user_id' => $user['id'])), true);
        }
    }

    public function get_project_chat_id()
    {
        $project = $this->getProject();
        //$this->checkCSRFParam();
        $apikey = $this->projectMetadataModel->get($project['id'], 'telegram_apikey', $this->configModel->get('telegram_apikey'));
        $bot_username = $this->projectMetadataModel->get($project['id'], 'telegram_username', $this->configModel->get('telegram_username'));
        $offset = 0 + $this->projectMetadataModel->get($project['id'], 'telegram_offset', $this->configModel->get('telegram_offset'));
        $private_message = mb_substr(urldecode($this->request->getStringParam('private_message')), 0, 32);

        list($offset, $chat_id, $user_name) = $this->get_chat_id($apikey, $bot_username, $offset, $private_message);

        if ($offset != 0)
        {
            //ok
            $this->projectMetadataModel->save($project['id'], array('telegram_offset' => $offset));
            $this->response->html($this->template->render('telegram:project/save_chat_id', array('chat_id' => $chat_id, 'user_name' => $user_name, 'private_message' => $private_message, 'bot_url' => "https://t.me/" . $bot_username, 'project' => $project)));
        }
        else
        {
            //error
            $this->response->redirect($this->helper->url->to('ProjectViewController', 'integrations', array('project_id' => $project['id'])), true);
        }
    }

    private function get_chat_id($apikey, $bot_username, $offset, $private_message)
    {
        try
        {
            if (empty($private_message) || mb_strlen($private_message) != 32)
            {
                throw new TelegramException("empty private_message!");
            }

            // Create Telegram API object
            $telegram = new TelegramClass($apikey, $bot_username);

            $limit = 100;
            $timeout = 1;
            $response = Request::getUpdates(['offset' => $offset + 1, 'limit' => $limit, 'timeout' => $timeout, ]);

            $chat_id = "";
            $user_name = "";

            if ($response->isOk())
            {
                //Process all updates
                
                /** @var Update $result */
                foreach ((array)$response->getResult() as $result)
                {
                    $offset = $result->getUpdateId();
                    if ($result->getMessage() != NULL)
                    {
                        if ($private_message === mb_substr(trim($result->getMessage()->getText()), 0, 32))
                        {
                            $chat_id = $result->getMessage()->getChat()->getId();
                            $user_name = $result->getMessage()->getChat()->getFirstName();
                            break;
                        }
                    }
                }
            }
            else
            {
                throw new TelegramException($response->printError(true));
            }
        }
        catch(TelegramException $e)
        {
            // log telegram errors
            error_log($e->getMessage());
            $this->flash->failure(t('Telegram error: ') . $e->getMessage());
            return 0; //$this->response->redirect($this->helper->url->to('UserViewController', 'integrations', array('user_id' => $user['id'] )), true);
            
        }

        return array($offset, $chat_id, $user_name);
    }

    public function save_user_chat_id()
    {
        $user = $this->getUser();
        $this->checkCSRFParam();

        $chat_id = urldecode($this->request->getStringParam('chat_id'));
        if (is_numeric($chat_id))
        {
            $this->userMetadataModel->save($user['id'], array('telegram_user_cid' => $chat_id));
            $this->flash->success(t("Chat id was updated to %s", $chat_id));
        }
        else
        {
            $this->flash->failure(t('Telegram error: wrong chat id'));
        }
        return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations', array('user_id' => $user['id'])), true);
    }

    public function save_project_chat_id()
    {
        $project = $this->getProject();
        $this->checkCSRFParam();

        $chat_id = urldecode($this->request->getStringParam('chat_id'));
        if (is_numeric($chat_id))
        {
            $this->projectMetadataModel->save($project['id'], array('telegram_group_cid' => $chat_id));
            $this->flash->success(t("Chat id was updated to %s", $chat_id));
        }
        else
        {
            $this->flash->failure(t('Telegram error: wrong chat id'));
        }
        return $this->response->redirect($this->helper->url->to('ProjectViewController', 'integrations', array('project_id' => $project['id'])), true);
    }
}
