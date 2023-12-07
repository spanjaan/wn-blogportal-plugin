<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('spanjaan/blogportal/comments') ?>">Comments</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>
<!-- ToolBar -->
<div class="control-toolbar">
    <div class="toolbar-item toolbar-primary">
        <div data-control="toolbar">
        <a href="<?= Backend::url('spanjaan/blogportal/comments') ?>" class="btn btn-default oc-icon-arrow-left"><?= e(trans('backend::lang.form.return_to_list')); ?></a>
        <a href="<?= $updateUrl ?>" type="button" class="btn btn-success oc-icon-pencil"><?= e(trans('Edit Comment/Change Status')) ?></a>
        </div>
    </div>
</div>

<?php if (!$this->fatalError): ?>

    <div class="form-preview">
        <?= $this->formRenderPreview() ?>
    </div>

<?php else: ?>

    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
    <p><a href="<?= Backend::url('spanjaan/blogportal/comments') ?>" class="btn btn-default"><?= e(trans('backend::lang.form.return_to_list')) ?></a></p>

<?php endif ?>

<p>
    Record <strong><?= e($currentIndex); ?></strong> of <strong><?= e($totalRecords); ?></strong>. <a href="<?= \Backend::url('spanjaan/blogportal/comments'); ?>">View all</a>
</p>
<p>
    <button
        type="button"
        class="btn btn-default"
        onClick="window.location.assign('<?= e($previousUrl); ?>');"
        <?php if (!$previousUrl) : ?>disabled="disabled"<?php endif; ?>> 
        <span class="oc-icon-chevron-left"></span> Previous
    </button>
    <button
        type="button"
        class="btn btn-default "
        onClick="window.location.assign('<?= e($nextUrl); ?>');"
        <?php if (!$nextUrl) : ?>disabled="disabled"<?php endif; ?>>
        Next <span class="oc-icon-chevron-right"></span>
    </button>
</p>