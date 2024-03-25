Telegram plugin for Kanboard
===============================

Receive Kanboard notifications on Telegram.

Author
------

- Manu Varkey
- License MIT

Requirements
------------

- Kanboard >= 1.2.22
- Telegram bot

Installation
------------

You have the choice between 2 methods:

1. Download the zip file and decompress everything under the directory `plugins/Telegram`
2. Clone this repository into the directory `plugins/Telegram`

Note: Plugin folder is case-sensitive.

Configuration
-------------

### Create a Telegram bot by starting a conversation with BotFather

Start a conversation with [BotFather](https://telegram.me/botfather) and follow the [guide](https://core.telegram.org/bots#6-botfather) to create a Telegram Bot.

Following Bot configuration should be set for proper functionality.

1. Bot Settings -> Allow Groups -> On
2. Bot Settings -> Group Admin Rights -> Manage Topics -> On
3. Bot Settings -> Group Admin Rights -> Manage Chat -> On
4. Bot Settings -> Group Privacy -> Off

### Telegram Bot Settings

Go to **Settings > Integrations > Telegram** and fill the form:

- **Telegram bot username**: Username of your Telegram Bot
- **Telegram bot API key**: HTTP API token generated by BotFather after bot creation

### Receive individual user notifications
- Go to your user profile and choose **Integrations > Telegram**
- Start a conversation with your Telegram Bot
- Send the unique message as displayed on the page to the chat
- Click on **Get Chat ID** and confirm
- Enable Telegram notifications in your profile: **Notifications > Select Telegram**
#### Manual
- Start a conversation with your Telegram Bot
- Obtain the chat id of the conversation (Send a message to the bot and visit `https://api.telegram.org/bot<YourBOTToken>/getUpdates`)
- Go to your user profile and choose **Integrations > Telegram**
- Enter the chat id of the chat
- Enable Telegram notifications in your profile: **Notifications > Select Telegram**

### Receive project notifications to a chat
- Go to the project settings and choose **Integrations > Telegram**
- Add your Telegram Bot to the project group chat
- Send the unique message as displayed on the page to the project group chat
- Click on **Get Chat ID** and confirm
- Enable Telegram notifications for your project: **Notifications > Select Telegram**
#### Manual
- Add your Telegram Bot to the project group chat
- Obtain the chat id of the conversation (Send a message to the group and visit `https://api.telegram.org/bot<YourBOTToken>/getUpdates`)
- Go to the project settings and choose **Integrations > Telegram**
- Enter the chat id of the group chat
- Enable Telegram notifications for your project: **Notifications > Select Telegram**


Troubleshooting
---------------

> I am getting `curl error 60: SSL certificate problem: self signed certificate in certificate chain` on Windows

- Download this CAs database `https://curl.haxx.se/ca/cacert.pem` to `c:/cacert.pem`
- Edit your php.ini and add `curl.cainfo="c:/cacert.pem"` (it should point to the file you downloaded)
