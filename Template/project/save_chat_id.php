<div class="page-header">
    <h2><?= t('Save Chat ID') ?></h2>
</div>

<?php if (empty($chat_id)) : ?>
    <div class="confirm">
        <p class="alert alert-info"><?= t('Message %s not found!', $private_message) ?></p>
        <p class="info"><?= t('Please send message %s ', $private_message) ?><br />
            to <a href="<?= $bot_url ?>" target="_blank" rel="noreferrer"><?= $bot_url ?></a></p>
        <br />
        <p>If you wish connect the bot to chat room, please ensure that the bot have admin rights!</p>
    </div>
<?php else : ?>
    <div class="confirm">
        <p class="alert alert-info">
            <?= t('Save chat id="%s" from "%s"?', $this->text->e($chat_id), $this->text->e($user_name)) ?>
            <?php if ($is_topic_message)
            { ?>
                <br>
                <?= t('Save topic id="%s"', $this->text->e($message_thread_id)) ?>
            <?php } ?>
        </p>

        <?= $this->modal->confirmButtons(
            'TelegramController',
            'save_project_chat_id',
            array('plugin' => 'Telegram', 'project_id' => $project['id'], 'chat_id' => $chat_id,  'is_topic_message' => $is_topic_message, 'message_thread_id' => $message_thread_id)
        ) ?>
    </div>
<?php endif ?>