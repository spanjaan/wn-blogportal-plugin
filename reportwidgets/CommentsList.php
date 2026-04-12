<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\ReportWidgets;

use BackendAuth;
use Backend\Classes\ReportWidgetBase;
use Cms\Classes\Controller;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Flash;
use Lang;
use SpAnjaan\BlogPortal\Models\Comment;
use ValidationException;

class CommentsList extends ReportWidgetBase
{
    /** @var bool */
    protected $widgetAssetsLoaded = false;

    /**
     * Initialize Widget
     *
     * @return void
     */
    public function init(): void
    {
        $this->loadAssets();
    }

    /**
     * Define Widget Properties
     *
     * @return array
     */
    public function defineProperties(): array
    {
        return [
            'postPage' => [
                'title'       => 'winter.blog::lang.settings.posts_post',
                'description' => 'winter.blog::lang.settings.posts_post_description',
                'type'        => 'dropdown',
                'default'     => 'blog/post',
            ],
            'defaultTab' => [
                'title'       => 'spanjaan.blogportal::lang.components.comments_list.default_tab',
                'description' => 'spanjaan.blogportal::lang.components.comments_list.default_tab_comment',
                'type'        => 'dropdown',
                'options'     => [
                    'pending'  => Lang::get('spanjaan.blogportal::lang.model.comments.statusPending'),
                    'approved' => Lang::get('spanjaan.blogportal::lang.model.comments.statusApproved'),
                    'rejected' => Lang::get('spanjaan.blogportal::lang.model.comments.statusRejected'),
                    'spam'     => Lang::get('spanjaan.blogportal::lang.model.comments.statusSpam'),
                ],
            ],
        ];
    }

    /**
     * Get Post Page Options
     *
     * @return array
     */
    public function getPostPageOptions(): array
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Load Widget Assets
     *
     * @return void
     */
    protected function loadAssets(): void
    {
        if ($this->widgetAssetsLoaded) {
            return;
        }

        $this->addCss('css/comment-list.css');
        $this->widgetAssetsLoaded = true;
    }

    /**
     * Render Widget
     *
     * @return string
     */
    public function render(): string
    {
        $defaultTab = $this->property('defaultTab', 'pending');
        if (!in_array($defaultTab, ['pending', 'approved', 'rejected', 'spam'], true)) {
            $defaultTab = 'pending';
        }

        $comments = Comment::where('status', $defaultTab)->orderBy('created_at', 'DESC')->get();

        $comment = null;
        if ($comments->count() > 0) {
            if (!empty($postPage = $this->property('postPage'))) {
                $controller = new Controller(Theme::getActiveTheme());
                $comments->each(function ($item) use ($postPage, $controller) {
                    $item->post->setUrl($postPage, $controller);
                });
            }
            $comment = $comments->shift();
        }

        return $this->makePartial('widget', [
            'status'          => $defaultTab,
            'counts'          => [
                'pending'  => Comment::where('status', 'pending')->count(),
                'approved' => Comment::where('status', 'approved')->count(),
                'rejected' => Comment::where('status', 'rejected')->count(),
                'spam'     => Comment::where('status', 'spam')->count(),
            ],
            'comment'         => $comment,
            'commentPartial'  => $this->makePartial('comment', [
                'comment' => $comment,
            ]),
            'list'            => $comments,
            'listPartial'    => $this->makePartial('list', [
                'list' => $comments,
            ]),
        ]);
    }

    /**
     * Change Tab AJAX Handler
     *
     * @return array
     */
    public function onChangeTab(): array
    {
        $tab = input('tab');
        if (!in_array($tab, ['pending', 'approved', 'rejected', 'spam'], true)) {
            $tab = 'pending';
        }

        $comments = Comment::where('status', $tab)->orderBy('created_at', 'DESC')->get();

        $comment = null;
        if ($comments->count() > 0) {
            if (!empty($postPage = $this->property('postPage'))) {
                $controller = new Controller(Theme::getActiveTheme());
                $comments->each(function ($item) use ($postPage, $controller) {
                    $item->post->setUrl($postPage, $controller);
                });
            }
            $comment = $comments->shift();
        }

        return [
            'tab'             => $tab,
            '#commentPartial' => $this->makePartial('comment', [
                'comment' => $comment,
            ]),
            '#listPartial'    => $this->makePartial('list', [
                'list' => $comments,
            ]),
        ];
    }

    /**
     * Change Comment AJAX Handler
     *
     * @return array
     */
    public function onChangeComment(): array
    {
        $commentId = intval(input('comment_id'));

        $comment = Comment::where('id', $commentId)->first();
        if ($comment && !empty($postPage = $this->property('postPage'))) {
            $comment->post->setUrl($postPage, new Controller(Theme::getActiveTheme()));
        }

        $comments = Comment::where('status', empty($comment) ? 'unknown' : $comment->status)
            ->where('id', '!=', $commentId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return [
            '#commentPartial' => $this->makePartial('comment', [
                'comment' => $comment,
            ]),
            '#listPartial'    => $this->makePartial('list', [
                'list' => $comments,
            ]),
        ];
    }

    /**
     * Change Status AJAX Handler
     *
     * @return array
     */
    public function onChangeStatus(): array
    {
        $status = input('status');
        $commentId = input('comment_id');
        $currentTab = input('tab', 'pending');

        if (!in_array($currentTab, ['pending', 'approved', 'rejected', 'spam'], true)) {
            $currentTab = 'pending';
        }

        if (!(BackendAuth::check() && BackendAuth::getUser()->hasPermission('spanjaan.blogportal.comments.moderate_comments'))) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.moderate_permission'));
        }

        if (!in_array($status, ['approve', 'reject', 'spam'], true)) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_status'));
        }

        $comment = Comment::where('id', $commentId)->first();
        if (empty($comment)) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_status'));
        }

        $result = $comment->{$status}();
        if ($result === false) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }

        $comments = Comment::where('status', $currentTab)->orderBy('created_at', 'DESC')->get();

        $comment = null;
        if ($comments->count() > 0) {
            if (!empty($postPage = $this->property('postPage'))) {
                $controller = new Controller(Theme::getActiveTheme());
                $comments->each(function ($item) use ($postPage, $controller) {
                    $item->post->setUrl($postPage, $controller);
                });
            }
            $comment = $comments->shift();
        }

        Flash::success(Lang::get('spanjaan.blogportal::lang.frontend.success.update_status'));

        return [
            'status'          => Lang::get('spanjaan.blogportal::lang.frontend.success.update_status'),
            'counts'          => [
                'pending'  => Comment::where('status', 'pending')->count(),
                'approved' => Comment::where('status', 'approved')->count(),
                'rejected' => Comment::where('status', 'rejected')->count(),
                'spam'     => Comment::where('status', 'spam')->count(),
            ],
            '#commentPartial' => $this->makePartial('comment', [
                'comment' => $comment,
            ]),
            '#listPartial'    => $this->makePartial('list', [
                'list' => $comments,
            ]),
        ];
    }
}
