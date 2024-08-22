<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item control-chart" data-control="chart-pie">
            <ul>
                <li data-color="#1877F2">Facebook <span><?= $this->getShareCount('facebook_count'); ?></span></li>
                <li data-color="#1DA1F2">Twitter <span><?= $this->getShareCount('twitter_count'); ?></span></li>
                <li data-color="#25D366">Whatsapp <span><?= $this->getShareCount('whatsapp_count'); ?></span></li>
                <li data-color="#0077B5">Linkedin<span><?= $this->getShareCount('linkedin_count'); ?></span></li>
            </ul>
        </div>

        <div class="scoreboard-item title-value">
            <h4>Total</h4>
            <p><?= $this->getShareCount('all_count'); ?></p>
            <p class="description">Shares</p>
        </div>

    </div>
</div>
<!-- <div data-control="toolbar">
    <button
        class="btn btn-danger wn-icon-trash-o"
        disabled="disabled"
        onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
        data-request="onDelete"
        data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')); ?>"
        data-trigger-action="enable"
        data-trigger=".control-list input[type=checkbox]"
        data-trigger-condition="checked"
        data-request-success="$(this).prop('disabled', 'disabled')"
        data-stripe-load-indicator>
        <?= e(trans('backend::lang.list.delete_selected')); ?>
    </button>
</div> -->
