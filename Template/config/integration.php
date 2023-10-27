<h3><img src="<?= $this->url->dir() ?>plugins/Telegram/Asset/telegram-icon.png"/>&nbsp;Telegram</h3>
<div class="panel">
    <?= $this->form->label(t('Telegram bot username'), 'telegram_username') ?>
    <?= $this->form->text('telegram_username', $values, array()) ?>

    <?= $this->form->label(t('Telegram bot API key'), 'telegram_apikey') ?>
    <?= $this->form->text('telegram_apikey', $values, array()) ?>

    <p class="form-help"><a href="https://core.telegram.org/bots" target="_blank"><?= t('Help on how to generate a bot') ?></a></p>

    <?= $this->form->label(t('Telegram proxy server'), 'telegram_proxy') ?>
    <?= $this->form->text('telegram_proxy', $values, array()) ?>

    <?= $this->form->hidden('forward_attachments', array('forward_attachments' => 0)) ?>
    <?= $this->form->checkbox('forward_attachments', t('Sent attachments along with notification'), 1, isset($values['forward_attachments']) && $values['forward_attachments'] == 1) ?>    
    
    <div class="form-actions">
        <input type="submit" value="<?= t('Save') ?>" class="btn btn-blue"/>
    </div>
</div>
