<?php

namespace Kanboard\Plugin\Telegram;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/Console/TelegramCommand.php';

use Kanboard\Core\Translator;
use Kanboard\Core\Plugin\Base;

/**
 * Telegram Plugin
 *
 * @package  telegram
 * @author   Manu Varkey
 */
class Plugin extends Base
{
    public function initialize()
    {
        $this->template->hook->attach('template:config:integrations', 'telegram:config/integration');
        $this->template->hook->attach('template:project:integrations', 'telegram:project/integration',array('bot_name' =>  $this->configModel->get('telegram_username')) );
        $this->template->hook->attach('template:user:integrations', 'telegram:user/integration',array('bot_name'=> $this->configModel->get('telegram_username')) );

        $this->userNotificationTypeModel->setType('telegram', t('Telegram'), '\Kanboard\Plugin\Telegram\Notification\Telegram');
        $this->projectNotificationTypeModel->setType('telegram', t('Telegram'), '\Kanboard\Plugin\Telegram\Notification\Telegram');

        $this->cli->add(new TelegramCommand($this->container));
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function getPluginDescription()
    {
        return 'Receive notifications on Telegram';
    }

    public function getPluginAuthor()
    {
        return 'Manu Varkey';
    }

    public function getPluginVersion()
    {
        return '1.2.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/manuvarkey/plugin-telegram';
    }

    public function getCompatibleVersion()
    {
        return '>=1.0.37';
    }
}
