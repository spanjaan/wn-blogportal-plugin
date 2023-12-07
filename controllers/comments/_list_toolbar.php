<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item control-chart" data-control="chart-pie">
            <ul>
                <li data-color="#38b000">Approved <span><?= $this->getCommentStats('approved_count'); ?></span></li>
                <li data-color="#ff006e">Pending <span><?= $this->getCommentStats('pending_count'); ?></span></li>
                <li data-color="#3a0ca3">Spam <span><?= $this->getCommentStats('spam_count'); ?></span></li>
                <li data-color="#D71313">Rejected<span><?= $this->getCommentStats('rejected_count'); ?></span></li>
            </ul>
        </div>

        <div class="scoreboard-item title-value">
            <h4>Total</h4>
            <p><?= $this->getCommentStats('all_count'); ?></p>
            <p class="description">Comments</p>
        </div>

    </div>
</div>
<div data-control="toolbar" class="loading-indicator-container">
    <button
        class="btn btn-danger oc-icon-trash-o"
        disabled="disabled"
        onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
        data-request="onDelete"
        data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')) ?>"
        data-trigger-action="enable"
        data-trigger=".control-list input[type=checkbox]"
        data-trigger-condition="checked"
        data-request-success="$(this).prop('disabled', 'disabled')"
        data-stripe-load-indicator>
        <?= e(trans('backend::lang.list.delete_selected')) ?>
    </button>
</div>
