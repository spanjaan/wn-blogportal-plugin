<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item control-chart" data-control="chart-pie">
            <ul>
                <li data-color="#27a14b">Approved <span><?= $this->getCommentStats('approved_count'); ?></span></li>
                <li data-color="#593dbe">Pending <span><?= $this->getCommentStats('pending_count'); ?></span></li>
                <li data-color="#db3636">Spam <span><?= $this->getCommentStats('spam_count'); ?></span></li>
                <li data-color="#da3ba7">Rejected <span><?= $this->getCommentStats('rejected_count'); ?></span></li>
            </ul>
        </div>
        <div class="scoreboard-item title-value">
            <h4>Total</h4>
            <p><?= $this->getCommentStats('all_count'); ?></p>
            <p class="description">Comments</p>
        </div>
    </div>
</div>