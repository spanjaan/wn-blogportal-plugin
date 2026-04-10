<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Lang;
use Config;
use Crypt;
use Request;
use Session;
use Response;
use Backend\Facades\BackendAuth;
use Cms\Classes\ComponentBase;
use Illuminate\Pagination\LengthAwarePaginator;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Models\BlogPortalSettings;
use SpAnjaan\BlogPortal\Models\Comment;
use SpAnjaan\BlogPortal\Models\Visitor;
use System\Classes\PluginManager;
use System\Classes\UpdateManager;
use ValidationException;

class CommentSection extends ComponentBase
{
    /**
     * Current Post
     *
     * @var ?Post
     */
    protected $post = null;

    /**
     * BlogPortal Settings
     *
     * @var ?BlogPortalSettings
     */
    protected $blogportalSettings = null;

    /**
     * Declare Component Details
     *
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'spanjaan.blogportal::lang.components.comments_section.label',
            'description' => 'spanjaan.blogportal::lang.components.comments_section.comment'
        ];
    }

    /**
     * Define Component Properties
     *
     * @return array
     */
    public function defineProperties()
    {
        return [
            'postSlug' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.post_slug',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.post_slug_comment',
                'type'              => 'string',
                'default'           => '',
            ],
            'commentsPerPage' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.comments_per_page',
                'type'              => 'string',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'winter.blog::lang.settings.posts_per_page_validation',
                'default'           => '10',
            ],
            'pageNumber' => [
                'title'             => 'winter.blog::lang.settings.posts_pagination',
                'description'       => 'winter.blog::lang.settings.posts_pagination_description',
                'type'              => 'string',
                'default'           => '',
            ],
            'sortOrder' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.comments_order',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.comments_order_comment',
                'type'              => 'dropdown',
                'default'           => 'created_at desc',
            ],
            'commentsAnchor' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.comments_anchor',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.comments_anchor_comment',
                'type'              => 'string',
                'default'           => 'comments'
            ],
            'pinFavorites' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.pin_favorites',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.pin_favorites_comment',
                'type'              => 'checkbox',
                'default'           => '0'
            ],
            'hideOnDislikes' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.hide_on_dislike',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.hide_on_dislike_comment',
                'type'              => 'checkbox',
                'default'           => '0'
            ],
            'formPosition' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.form_position',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.form_position_comment',
                'type'              => 'dropdown',
                'default'           => 'above',
                'useSearch'         => false,
                'options'           => [
                    'above'             => 'spanjaan.blogportal::lang.components.comments_section.form_position_above',
                    'below'             => 'spanjaan.blogportal::lang.components.comments_section.form_position_below',
                ]
            ],
            'disableForm' => [
                'title'             => 'spanjaan.blogportal::lang.components.comments_section.disable_form',
                'description'       => 'spanjaan.blogportal::lang.components.comments_section.disable_form_comment',
                'type'              => 'checkbox',
                'default'           => '0'
            ],
        ];
    }

    /**
     * Get BlogPortal Settings
     *
     * @param string $key
     * @return mixed
     */
    protected function config(string $key)
    {
        if (empty($this->blogportalSettings)) {
            $this->blogportalSettings = BlogPortalSettings::instance();
        }
        return $this->blogportalSettings->{$key} ?? BlogPortalSettings::defaultValue($key);
    }

    /**
     * Get Sort Order Dropdown Options
     *
     * @return array
     */
    public function getSortOrderOptions()
    {
        return [
            'created_at DESC'   => Lang::get('spanjaan.blogportal::lang.sorting.created_at_desc'),
            'created_at ASC'    => Lang::get('spanjaan.blogportal::lang.sorting.created_at_asc'),
            'likes DESC'        => Lang::get('spanjaan.blogportal::lang.sorting.likes_desc'),
            'likes ASC'         => Lang::get('spanjaan.blogportal::lang.sorting.likes_asc'),
            'dislikes DESC'     => Lang::get('spanjaan.blogportal::lang.sorting.dislikes_desc'),
            'dislikes ASC'      => Lang::get('spanjaan.blogportal::lang.sorting.dislikes_asc'),
        ];
    }

    /**
     * Get Current Post
     *
     * @return Post|null
     */
    protected function getPost()
    {
        $slug = $this->property('postSlug');
        if (empty($slug)) {
            if (!empty($component = $this->controller->getPage()->getComponent('blogPost'))) {
                $slug = $component->getProperties()['slug'];

                if (($last = strpos($slug, '}}')) > 0) {
                    if (($index = strpos($slug, ':')) > 0) {
                        $slug = trim(substr($slug, $index + 1, $last - 4));
                        $slug = $this->param($slug);
                    } else {
                        $slug = null;
                    }
                }
            }

            if (empty($slug)) {
                if (empty($slug = $this->param('slug'))) {
                    return null;
                }
            }
        }

        return Post::where('slug', $slug)->first();
    }

    /**
     * Build base comment query with permissions and filters applied
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildBaseQuery()
    {
        $order = $this->property('sortOrder');
        if (!array_key_exists($order, $this->getSortOrderOptions())) {
            $order = 'created_at DESC';
        }

        $query = Comment::where('post_id', $this->post->id);

        // Apply permission filter
        if ($this->page['currentUserCanModerate']) {
            $query->whereIn('status', ['approved', 'pending']);
        } else {
            $query->where(function ($builder) {
                $builder->where('status', 'approved')->orWhere(function ($builder) {
                    $builder->where('status', 'pending')
                        ->where('author_id', Visitor::currentUser()->id)
                        ->where('author_table', 'SpAnjaan\\BlogPortal\\Models\\Visitor');
                });
            });
        }

        // Pin Favorites
        if ($this->property('pinFavorites') === '1') {
            $query->orderByDesc('favorite');
        }

        // Apply sort order
        $orders = explode(' ', $order);
        $query->orderBy($orders[0], strtoupper($orders[1]) === 'DESC' ? 'DESC' : 'ASC');

        // Hide on Dislike
        if (($value = $this->property('hideOnDislikes')) !== '0') {
            if (strpos($value, ':') === 0 && is_numeric(substr($value, 1))) {
                $val = substr($value, 1);
                $query->whereRaw("(dislikes == 0 OR dislikes / likes < $val)");
            } else {
                $query->where('dislikes', '<', $value);
            }
        }

        return $query;
    }

    /**
     * Get Comment List
     *
     * @return LengthAwarePaginator
     */
    protected function getComments()
    {
        $page = empty($this->property('pageNumber'))
            ? max(1, intval(get('cpage')))
            : max(1, intval($this->property('pageNumber')));

        $limit = max(1, intval($this->property('commentsPerPage')));
        $offset = ($page - 1) * $limit;

        $baseQuery = $this->buildBaseQuery();

        // Get root comment IDs and count in a single optimized query
        $rootIds = $baseQuery
            ->whereNull('parent_id')
            ->orderByDesc('favorite')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->pluck('id')
            ->toArray();

        // Get total count separately (no need to clone entire query structure)
        $totalRoots = Comment::where('post_id', $this->post->id)
            ->whereNull('parent_id')
            ->where(function ($q) use ($baseQuery) {
                // Re-apply permission filter
                if ($this->page['currentUserCanModerate']) {
                    $q->whereIn('status', ['approved', 'pending']);
                } else {
                    $q->where('status', 'approved');
                }
            })
            ->count();

        // Early return if no comments
        if (empty($rootIds)) {
            $pageName = $this->getPage()->getBaseFileName();
            return new LengthAwarePaginator(
                collect(),
                0,
                $limit,
                $page,
                [
                    'path'     => $this->controller->pageUrl($pageName, ['slug' => $this->post->slug]),
                    'fragment' => $this->property('commentsAnchor'),
                    'pageName' => 'cpage'
                ]
            );
        }

        // Collect all descendant IDs in a single query using closure
        $allIds = $rootIds;
        $parentIds = $rootIds;
        
        while (!empty($parentIds)) {
            $childIds = Comment::whereIn('parent_id', $parentIds)
                ->where('post_id', $this->post->id)
                ->where(function ($q) {
                    if ($this->page['currentUserCanModerate']) {
                        $q->whereIn('status', ['approved', 'pending']);
                    } else {
                        $q->where('status', 'approved');
                    }
                })
                ->pluck('id')
                ->toArray();

            if (empty($childIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $parentIds = $childIds;
        }

        // Get nested comments
        $nested = Comment::whereIn('id', $allIds)
            ->orderByDesc('favorite')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toNested();

        $pageName = $this->getPage()->getBaseFileName();
        return new LengthAwarePaginator(
            $nested,
            $totalRoots,
            $limit,
            $page,
            [
                'path'     => $this->controller->pageUrl($pageName, ['slug' => $this->post->slug]),
                'fragment' => $this->property('commentsAnchor'),
                'pageName' => 'cpage'
            ]
        );
    }

    /**
     * Get Current User
     *
     * @return Winter\User\Models\User|Backend\Models\User|null
     */
    protected function getCurrentUser()
    {
        if (($user = $this->getBackendUser()) !== null) {
            return $user;
        } elseif (($user = $this->getFrontendUser()) !== null) {
            return $user;
        } else {
            return null;
        }
    }

    /**
     * Get Frontend User (when Winter.User is installed)
     *
     * @return Winter\User\Models\User|null
     */
    protected function getFrontendUser()
    {
        if (PluginManager::instance()->hasPlugin('Winter.User')) {
            $winterAuth = \Winter\User\Classes\AuthManager::instance();

            if ($winterAuth->check()) {
                return $winterAuth->getUser();
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Get Backend User
     *
     * @return Backend\Models\User|null
     */
    protected function getBackendUser()
    {
        if (BackendAuth::check()) {
            return BackendAuth::getUser();
        } else {
            return null;
        }
    }

    /**
     * Check if someone is logged in
     *
     * @return boolean
     */
    protected function isSomeoneLoggedIn()
    {
        return $this->getCurrentUser() !== null;
    }

    /**
     * Run Component
     *
     * @return mixed
     */
    public function onRun()
    {
        $this->post = $post = $this->getPost();

        if (empty($post) || (!empty($post) && $post->spanjaan_blogportal_comment_visible === '0')) {
            $this->page['showComments'] = false;
            $this->page['showCommentsForm'] = false;
            $this->page['comments'] = null;
            $this->page['commentsFormPosition'] = $this->property('formPosition');
            $this->page['commentsMode'] = 'closed';
            $this->page['currentUser'] = null;
            $this->page['currentUserIsGuest'] = true;
            $this->page['currentUserIsFrontend'] = false;
            $this->page['currentUserIsBackend'] = false;
            $this->page['isLoggedIn'] = false;
            $this->page['currentUserCanLike'] = false;
            $this->page['currentUserCanDislike'] = false;
            $this->page['currentUserCanFavorite'] = false;
            $this->page['currentUserCanComment'] = false;
            $this->page['currentUserCanModerate'] = false;
        } else {
            $this->prepareVars($post);

            if ($this->page['showCommentFormCaptcha']) {
                $builder = (new \Gregwar\Captcha\CaptchaBuilder())->build();
                Session::put('blogportalCaptchaPhrase', $builder->getPhrase());
                $this->page['captchaImage'] = $builder->inline();
            }

            $this->page['comments'] = $this->getComments();
            $this->page['commentsCount'] = $this->getCommentsCount();

            $this->addJs('/plugins/spanjaan/blogportal/assets/js/comments.js');
            $this->addCss('/plugins/spanjaan/blogportal/assets/css/comments.css');
        }
    }

    /**
     * Prepare Page Variables
     *
     * @param Post $post
     * @return void
     */
    protected function prepareVars(Post $post)
    {
        $user = $this->getCurrentUser();
        $like = $this->config('like_comment') === '1';
        $dislike = $this->config('dislike_comment') === '1';
        $restrict = $this->config('restrict_to_users') === '1';
        $favorite = $this->config('author_favorites') === '1';

        // Show Comments
        $this->page['showComments'] = true;
        $this->page['commentsFormPosition'] = $this->property('formPosition');

        if ($this->property('disableForm') === '1') {
            $this->page['commentsMode'] = 'hidden';
            $this->page['showCommentsForm'] = false;
        } else {
            $this->page['showCommentsForm'] = true;
        }

        // Set Comments Mode
        $this->page['commentsMode'] = $post->spanjaan_blogportal_comment_mode;
        if ($this->config('guest_comments') === '0' && $this->page['commentsMode'] === 'open') {
            $this->page['commentsMode'] = 'restricted';
        }

        // Set currentUserCanComment
        if ($this->page['commentsMode'] === 'open') {
            $this->page['currentUserCanComment'] = true;
        } elseif ($this->page['commentsMode'] === 'restricted') {
            $this->page['currentUserCanComment'] = $this->isSomeoneLoggedIn();
        } elseif ($this->page['commentsMode'] === 'private') {
            $this->page['currentUserCanComment'] = $this->getBackendUser() !== null;
        } elseif ($this->page['commentsMode'] === 'closed') {
            $this->page['currentUserCanComment'] = false;
        } else {
            $this->page['showCommentsForm'] = false;
            $this->page['currentUserCanComment'] = false;
        }

        // Current user info
        $this->page['currentUser'] = $user;
        $this->page['currentUserIsGuest'] = !$this->isSomeoneLoggedIn();
        $this->page['currentUserIsFrontend'] = $this->getFrontendUser() !== null;
        $this->page['currentUserIsBackend'] = $this->getBackendUser() !== null;
        $this->page['isLoggedIn'] = $this->isSomeoneLoggedIn();

        $this->page['currentUserCanLike'] = $like && (!$restrict || $this->page['isLoggedIn']);
        $this->page['currentUserCanDislike'] = $dislike && (!$restrict || $this->page['isLoggedIn']);
        $this->page['currentUserCanFavorite'] = $favorite && $user && intval($user->id) === intval($post->user_id);
        $this->page['currentUserCanModerate'] = $this->page['currentUserIsBackend'] && $user && $user->hasPermission('spanjaan.blogportal.comments.moderate_comments');

        // Skip when no comment form is shown
        if (!$this->page['currentUserCanComment']) {
            return;
        }

        // Comment Form Fields
        $this->page['showCommentFormTitle'] = $this->config('form_comment_title') === '1';
        $this->page['allowCommentFormMarkdown'] = $this->config('form_comment_markdown') === '1';
        $this->page['showCommentFormTos'] = $this->config('form_tos_checkbox') === '1';
        if ($this->page['showCommentFormTos'] && $this->config('form_tos_hide_on_user') === '1' && $this->page['isLoggedIn']) {
            $this->page['showCommentFormTos'] = false;
        } else {
            $this->page['commentFormTosLabel'] = BlogPortalSettings::instance()->getTermsOfServiceLabel();
        }

        // Captcha
        if ($this->config('form_comment_captcha') === '1' && !$this->page['isLoggedIn']) {
            $hasCaptcha = true;
            $this->page['showCommentFormCaptcha'] = true;
        } else {
            $hasCaptcha = false;
            $this->page['showCommentFormCaptcha'] = false;
        }

        // Honeypot
        if ($this->config('form_comment_honeypot') === '1') {
            $hasHoneypot = true;
            $time = time();
            $hash = md5(strval($time));

            $this->page['showCommentFormHoneypot'] = true;
            $this->page['honeypotUser'] = 'comment_user' . $hash;
            $this->page['honeypotEmail'] = 'comment_email' . $hash;
            $this->page['honeypotTime'] = $time;
        } else {
            $hasHoneypot = false;
            $this->page['showCommentFormHoneypot'] = false;
        }

        // Validation fields
        $this->page['validationTime'] = time();
        
        // Calculate salt value: 0=none, 5=honeypot, 10=captcha, 15=both
        $saltValue = ($hasCaptcha ? 10 : 0) + ($hasHoneypot ? 5 : 0);
        $this->page['validationHash'] = hash_hmac(
            'SHA256',
            strval($this->page['validationTime']),
            strval($saltValue)
        );
    }

    /**
     * Verify CSRF and Session Token
     *
     * @return bool
     */
    protected function verifyCsrfToken()
    {
        if (!Config::get('system.enable_csrf_protection', true)) {
            return true;
        }

        if (in_array(Request::method(), ['HEAD', 'GET', 'OPTIONS'])) {
            return true;
        }

        $token = Request::input('_token') ?: Request::header('X-CSRF-TOKEN');

        if (!$token && $header = Request::header('X-XSRF-TOKEN')) {
            $token = Crypt::decrypt($header, false);
        }

        if (!strlen($token) || !strlen(Session::token())) {
            return false;
        }

        return hash_equals(Session::token(), $token);
    }

    /**
     * Verify Comment Validation Code
     *
     * @param string $code
     * @param string $time
     * @param boolean $hasCaptcha
     * @param boolean $hasHoneypot
     * @return boolean
     */
    protected function verifyValidationCode(string $code, string $time, bool &$hasCaptcha, bool &$hasHoneypot)
    {
        // Salt values: 0 = none, 5 = honeypot, 10 = captcha, 15 = both
        $saltNone     = '0';
        $saltHoneypot = '5';
        $saltCaptcha  = '10';
        $saltBoth    = '15';
        
        if (hash_equals(hash_hmac('SHA256', $time, $saltNone), $code)) {
            return true;
        }

        if (hash_equals(hash_hmac('SHA256', $time, $saltHoneypot), $code)) {
            $hasHoneypot = true;
            return true;
        }

        if (hash_equals(hash_hmac('SHA256', $time, $saltCaptcha), $code)) {
            $hasCaptcha = true;
            return true;
        }

        if (hash_equals(hash_hmac('SHA256', $time, $saltBoth), $code)) {
            $hasCaptcha = true;
            $hasHoneypot = true;
            return true;
        }

        return false;
    }

    /**
     * Validate AJAX Method
     *
     * @return Comment
     */
    protected function validateAjaxMethod()
    {
        if (empty($post = $this->getPost())) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_post'));
        }
        $this->prepareVars($post);

        if (empty($comment_id = input('comment_id'))) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.missing_comment_id'));
        }
        if (empty($comment = Comment::where('id', $comment_id)->first())) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_comment_id'));
        }

        return $comment;
    }

    /**
     * Get visible comment count for the post, respecting permissions
     *
     * @return int
     */
    protected function getCommentsCount(): int
    {
        if (empty($this->post)) {
            return 0;
        }

        $query = Comment::where('post_id', $this->post->id);

        if ($this->page['currentUserCanModerate']) {
            $query->whereIn('status', ['approved', 'pending']);
        } else {
            $query->where(function ($builder) {
                $builder->where('status', 'approved')->orWhere(function ($builder) {
                    $builder->where('status', 'pending')
                        ->where('author_id', Visitor::currentUser()->id)
                        ->where('author_table', 'SpAnjaan\\BlogPortal\\Models\\Visitor');
                });
            });
        }

        return $query->count();
    }

    /**
     * AJAX Handler - Change Comment Status (approve, reject, spam, favorite)
     *
     * @return array
     */
    public function onChangeStatus()
    {
        $comment = $this->validateAjaxMethod();

        if (empty($status = input('status')) || !in_array($status, ['favorite', 'approve', 'reject', 'spam'])) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }

        if ($status === 'favorite') {
            if ($this->config('author_favorites') !== '1') {
                throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.disabled_method'));
            }
            if (!$this->page['currentUserCanFavorite']) {
                throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to'));
            }
            $comment->favorite = !$comment->favorite;
            $result = $comment->save();
        } elseif ($status === 'approve' || $status === 'reject' || $status === 'spam') {
            if (!$this->page['currentUserIsBackend']) {
                throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to'));
            }
            if (!$this->page['currentUserCanModerate']) {
                throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.no_permissions_for'));
            }
            if ($comment->status !== 'pending') {
                throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
            }
            $result = $comment->{$status}();
        }

        if (isset($result) && $result) {
            return [
                'status' => 'success',
                'comment' => in_array($status, ['reject', 'spam']) ? null : $this->renderPartial('@_single', [
                    'comment' => $comment
                ])
            ];
        } else {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }
    }

    /**
     * AJAX Handler - Change Comment Vote (like, dislike)
     *
     * @return array
     */
    public function onVote()
    {
        $comment = $this->validateAjaxMethod();

        if (empty($vote = input('vote')) || !in_array($vote, ['like', 'dislike'])) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }

        if ($this->config($vote . '_comment') !== '1') {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.disabled_method'));
        }

        if (!$this->page['currentUserCan' . ucfirst($vote)]) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to'));
        }

        if ($comment->{$vote}()) {
            return [
                'status' => 'success',
                'comment' => $this->renderPartial('@_single', [
                    'comment' => $comment
                ])
            ];
        } else {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }
    }

    /**
     * AJAX Handler - Create a new Reply
     *
     * @return array
     */
    public function onCreateReply()
    {
        $comment = $this->validateAjaxMethod();

        if (!$this->page['showCommentsForm']) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.form_disabled'));
        }

        if (!$this->page['currentUserCanComment']) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to_comment'));
        }

        return [
            'status' => 'success',
            'reply' => $this->renderPartial('@_reply', [
                'comment' => $comment
            ]),
            'comment' => $comment,
            'submitText' => Lang::get('spanjaan.blogportal::lang.frontend.comments.submit_reply')
        ];
    }

    /**
     * AJAX Handler - Cancel current Reply
     *
     * @return array
     */
    public function onCancelReply()
    {
        return [
            'submitText' => Lang::get('spanjaan.blogportal::lang.frontend.comments.submit_comment')
        ];
    }

    /**
     * AJAX Handler - Reload Captcha
     *
     * @return array
     */
    public function onReloadCaptcha()
    {
        $builder = (new \Gregwar\Captcha\CaptchaBuilder())->build();
        Session::put('blogportalCaptchaPhrase', $builder->getPhrase());

        return [
            'captchaImage' => $builder->inline()
        ];
    }

    /**
     * AJAX Handler - Write a new Comment or Reply
     *
     * @return mixed
     */
    public function onComment()
    {
        if (empty($post = $this->getPost())) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_post'));
        }
        $this->prepareVars($post);

        // Validate CSRF Token
        if (!$this->verifyCsrfToken()) {
            throw new ValidationException([
                'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_csrf_token')
            ]);
        }

        // Validate Comment Validation Code
        $hasCaptcha = false;
        $hasHoneypot = false;
        if (!$this->verifyValidationCode(input('comment_validation'), input('comment_time'), $hasCaptcha, $hasHoneypot)) {
            throw new ValidationException([
                'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_validation_code')
            ]);
        }

        // Validate Captcha
        if ($hasCaptcha) {
            if (strtoupper(Session::get('blogportalCaptchaPhrase')) !== strtoupper(input('comment_captcha'))) {
                $builder = (new \Gregwar\Captcha\CaptchaBuilder())->build();
                Session::put('blogportalCaptchaPhrase', $builder->getPhrase());

                return Response::json([
                    'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_captcha') . Session::get('blogportalCaptchaPhrase'),
                    'captchaImage' => $builder->inline()
                ], 500);
            }
        }

        // Validate Honeypot
        if ($hasHoneypot) {
            $honey = input('comment_honey');

            if (empty($honey) || !empty(input('comment_user')) || !empty(input('comment_email'))) {
                throw new ValidationException([
                    'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.honeypot_filled')
                ]);
            }

            $honey = md5($honey);
        }

        // Check current User
        if (!$this->page['currentUserCanComment']) {
            throw new ValidationException(['message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to_commentd')]);
        }

        // Validate Terms of Service
        if ($this->page['showCommentFormTos'] && input('comment_tos') !== '1') {
            throw new ValidationException(['message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.tos_not_accepted')]);
        }

        // Build Comment
        $comment = new Comment([
            'status' => 'pending',
            'content' => input('comment_comment')
        ]);

        if ($this->page['currentUser']) {
            $comment->authorable = $this->page['currentUser'];

            if ($this->config('moderate_user_comments') === '0' || $this->getBackendUser() !== null) {
                $comment->status = 'approved';
            }
        } else {
            $comment->author = isset($honey) ? input('comment_user' . $honey) : input('comment_user');
            $comment->author_email = isset($honey) ? input('comment_email' . $honey) : input('comment_email');
            $comment->author_uid = sha1(request()->ip());
            $comment->authorable = Visitor::currentUser();

            if ($this->config('moderate_guest_comments') === '0') {
                $comment->status = 'approved';
            }
        }

        // Set Comment Title
        if ($this->config('form_comment_title')) {
            $comment->title = input('comment_title');
        }

        // Set Related Post
        $comment->post = $post;

        // Validate Comment Parent
        $parentId = input('comment_parent');
        if (!empty($parentId)) {
            if (empty($parent = Comment::where('id', $parentId)->first())) {
                throw new ValidationException([
                    'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.parent_not_found')
                ]);
            }

            if (intval($parent->post_id) !== intval($post->id)) {
                throw new ValidationException([
                    'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.parent_invalid')
                ]);
            }

            $comment->parent_id = $parent->id;
        }

        if ($comment->save()) {
            $this->post = $this->getPost();

            if ($this->page['showCommentFormCaptcha']) {
                $builder = (new \Gregwar\Captcha\CaptchaBuilder())->build();
                Session::put('blogportalCaptchaPhrase', $builder->getPhrase());
                $this->page['captchaImage'] = $builder->inline();
            }

            $this->page['comments'] = $this->getComments();
            $this->page['commentsCount'] = $this->getCommentsCount();

            return [
                'status' => 'success',
                'comments' => $this->renderPartial('@default'),
                'message' => 'Comment added successfully'
            ];
        } else {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }
    }
}