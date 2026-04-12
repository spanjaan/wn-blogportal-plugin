<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Lang;
use Log;
use Config;
use Crypt;
use Request;
use Session;
use Response;
use DB;
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
    public const VALIDATION_CODE_EXPIRY_SECONDS = 300;
    public const RATE_LIMIT_COMMENTS_PER_MINUTE = 5;
    public const RATE_LIMIT_VOTES_PER_MINUTE = 30;
    public const RATE_LIMIT_WINDOW_SECONDS = 60;

    protected $post = null;

    protected $blogportalSettings = null;

    /**
     * Define Component Details
     *
     * @return array
     */
    public function componentDetails(): array
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
    public function defineProperties(): array
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

    protected function config(string $key): mixed
    {
        if (empty($this->blogportalSettings)) {
            $this->blogportalSettings = BlogPortalSettings::instance();
        }
        return $this->blogportalSettings->{$key} ?? BlogPortalSettings::defaultValue($key);
    }

    public function getSortOrderOptions(): array
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

    protected function getPost(): ?Post
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

    protected function buildBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $order = $this->property('sortOrder');
        if (!array_key_exists($order, $this->getSortOrderOptions())) {
            $order = 'created_at DESC';
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

        if ($this->property('pinFavorites') === '1') {
            $query->orderByDesc('favorite');
        }

        $orders = explode(' ', $order);
        $query->orderBy($orders[0], strtoupper($orders[1]) === 'DESC' ? 'DESC' : 'ASC');

        if (($value = $this->property('hideOnDislikes')) !== '0') {
            if (strpos($value, ':') === 0 && is_numeric(substr($value, 1))) {
                $val = floatval(substr($value, 1));
                $query->where(function ($q) use ($val) {
                    $q->where('dislikes', 0)
                      ->orWhereRaw('dislikes / NULLIF(likes, 0) < ?', [$val]);
                });
            } else {
                $query->where('dislikes', '<', intval($value));
            }
        }

        return $query;
    }

    protected function getComments(): LengthAwarePaginator
    {
        $page = empty($this->property('pageNumber'))
            ? max(1, intval(get('cpage')))
            : max(1, intval($this->property('pageNumber')));

        $limit = max(1, intval($this->property('commentsPerPage')));
        $offset = ($page - 1) * $limit;

        $baseQuery = $this->buildBaseQuery();

        $rootIds = $baseQuery
            ->whereNull('parent_id')
            ->orderByDesc('favorite')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->pluck('id')
            ->toArray();

        $totalRoots = Comment::where('post_id', $this->post->id)
            ->whereNull('parent_id')
            ->where(function ($q) {
                if ($this->page['currentUserCanModerate']) {
                    $q->whereIn('status', ['approved', 'pending']);
                } else {
                    $q->where('status', 'approved');
                }
            })
            ->count();

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

        $allIds = array_merge($rootIds, $this->getAllChildCommentIds($rootIds));

        $flat = Comment::whereIn('id', $allIds)
            ->orderByDesc('favorite')
            ->orderBy('created_at', 'asc')
            ->get();

        $nested = $this->nestComments($flat);

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

    protected function nestComments(\Illuminate\Support\Collection $flat, ?int $parentId = null): \Illuminate\Support\Collection
    {
        return $flat
            ->filter(function ($c) use ($parentId) {
                if ($parentId === null) {
                    return $c->parent_id === null;
                }
                return (int) $c->parent_id === $parentId;
            })
            ->values()
            ->each(function ($comment) use ($flat) {
                $comment->children = $this->nestComments($flat, (int) $comment->id);
            });
    }

    protected function getAllChildCommentIds(array $rootIds): array
    {
        if (empty($rootIds)) {
            return [];
        }

        $canModerate = $this->page['currentUserCanModerate'];
        $statusCondition = $canModerate
            ? "status IN ('approved', 'pending')"
            : "status = 'approved'";

        $ids = array_map('intval', $rootIds);
        $placeholders = implode(',', $ids);

        $sql = "
            WITH RECURSIVE comment_tree AS (
                SELECT c.id FROM spanjaan_blogportal_comments c
                WHERE parent_id IN ($placeholders) AND post_id = ? AND $statusCondition
                UNION ALL
                SELECT c.id FROM spanjaan_blogportal_comments c
                INNER JOIN comment_tree ct ON c.parent_id = ct.id
                WHERE c.post_id = ? AND $statusCondition
            )
            SELECT id FROM comment_tree
        ";

        try {
            $result = \DB::select($sql, [$this->post->id, $this->post->id]);
            return array_column($result, 'id');
        } catch (\Throwable $e) {
            Log::error('BlogPortal: Failed to get child comments with CTE: ' . $e->getMessage());
            return $this->getAllChildCommentIdsFallback($rootIds);
        }
    }

    protected function getAllChildCommentIdsFallback(array $parentIds): array
    {
        $allIds = [];
        $currentIds = $parentIds;
        $maxDepth = 10;

        for ($i = 0; $i < $maxDepth; $i++) {
            if (empty($currentIds)) {
                break;
            }

            $children = Comment::whereIn('parent_id', $currentIds)
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

            if (empty($children)) {
                break;
            }

            $allIds = array_merge($allIds, $children);
            $currentIds = $children;
        }

        return $allIds;
    }

    protected function getCurrentUser()
    {
        if (($user = $this->getBackendUser()) !== null) {
            return $user;
        } elseif (($user = $this->getFrontendUser()) !== null) {
            return $user;
        }
        return null;
    }

    protected function getFrontendUser()
    {
        if (PluginManager::instance()->hasPlugin('Winter.User')) {
            $winterAuth = \Winter\User\Classes\AuthManager::instance();

            if ($winterAuth->check()) {
                return $winterAuth->getUser();
            }
        }
        return null;
    }

    protected function getBackendUser()
    {
        if (BackendAuth::check()) {
            return BackendAuth::getUser();
        }
        return null;
    }

    protected function isSomeoneLoggedIn(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    public function onRun(): void
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

    protected function prepareVars(Post $post): void
    {
        $user = $this->getCurrentUser();
        $like = $this->config('like_comment') === '1';
        $dislike = $this->config('dislike_comment') === '1';
        $restrict = $this->config('restrict_to_users') === '1';
        $favorite = $this->config('author_favorites') === '1';

        $this->page['showComments'] = true;
        $this->page['commentsFormPosition'] = $this->property('formPosition');

        if ($this->property('disableForm') === '1') {
            $this->page['commentsMode'] = 'hidden';
            $this->page['showCommentsForm'] = false;
        } else {
            $this->page['showCommentsForm'] = true;
        }

        $this->page['commentsMode'] = $post->spanjaan_blogportal_comment_mode;
        if ($this->config('guest_comments') === '0' && $this->page['commentsMode'] === 'open') {
            $this->page['commentsMode'] = 'restricted';
        }

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

        $this->page['currentUser'] = $user;
        $this->page['currentUserIsGuest'] = !$this->isSomeoneLoggedIn();
        $this->page['currentUserIsFrontend'] = $this->getFrontendUser() !== null;
        $this->page['currentUserIsBackend'] = $this->getBackendUser() !== null;
        $this->page['isLoggedIn'] = $this->isSomeoneLoggedIn();

        $this->page['currentUserCanLike'] = $like && (!$restrict || $this->page['isLoggedIn']);
        $this->page['currentUserCanDislike'] = $dislike && (!$restrict || $this->page['isLoggedIn']);
        $this->page['currentUserCanFavorite'] = $favorite && $user && intval($user->id) === intval($post->user_id);
        $this->page['currentUserCanModerate'] = $this->page['currentUserIsBackend'] && $user && $user->hasPermission('spanjaan.blogportal.comments.moderate_comments');

        if (!$this->page['currentUserCanComment']) {
            return;
        }

        $this->page['showCommentFormTitle'] = $this->config('form_comment_title') === '1';
        $this->page['allowCommentFormMarkdown'] = $this->config('form_comment_markdown') === '1';
        $this->page['showCommentFormTos'] = $this->config('form_tos_checkbox') === '1';
        if ($this->page['showCommentFormTos'] && $this->config('form_tos_hide_on_user') === '1' && $this->page['isLoggedIn']) {
            $this->page['showCommentFormTos'] = false;
        } else {
            $this->page['commentFormTosLabel'] = BlogPortalSettings::instance()->getTermsOfServiceLabel();
        }

        if ($this->config('form_comment_captcha') === '1' && !$this->page['isLoggedIn']) {
            $hasCaptcha = true;
            $this->page['showCommentFormCaptcha'] = true;
        } else {
            $hasCaptcha = false;
            $this->page['showCommentFormCaptcha'] = false;
        }

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

        $this->page['validationTime'] = time();
        $saltValue = ($hasCaptcha ? 10 : 0) + ($hasHoneypot ? 5 : 0);
        $this->page['validationHash'] = hash_hmac(
            'SHA256',
            strval($this->page['validationTime']),
            strval($saltValue)
        );
    }

    protected function verifyCsrfToken(): bool
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

    protected function verifyValidationCode(string $code, string $time, bool &$hasCaptcha, bool &$hasHoneypot): bool
    {
        $requestTime = intval($time);
        $currentTime = time();
        
        if (abs($currentTime - $requestTime) > self::VALIDATION_CODE_EXPIRY_SECONDS) {
            Log::info('BlogPortal: Validation code expired', [
                'request_time' => $requestTime,
                'current_time' => $currentTime,
                'difference' => abs($currentTime - $requestTime)
            ]);
            return false;
        }

        $saltNone     = '0';
        $saltHoneypot = '5';
        $saltCaptcha  = '10';
        $saltBoth     = '15';

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

    protected function validateAjaxMethod(): Comment
    {
        if (empty($post = $this->getPost())) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_post'));
        }
        $this->prepareVars($post);

        if (empty($comment_id = input('comment_id'))) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.missing_comment_id'));
        }
        
        $commentId = intval($comment_id);
        if ($commentId <= 0) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_comment_id'));
        }
        
        $comment = Comment::where('id', $commentId)->first();
        if (empty($comment)) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_comment_id'));
        }

        return $comment;
    }

    protected function checkRateLimit(string $action): bool
    {
        $visitorId = Visitor::currentUser()->id ?? 'anonymous';
        $sessionKey = 'blogportal_rate_' . $action . '_' . $visitorId;
        
        $now = time();
        $windowStart = $now - self::RATE_LIMIT_WINDOW_SECONDS;
        
        $rateData = Session::get($sessionKey, ['count' => 0, 'window_start' => $now]);
        
        if ($rateData['window_start'] < $windowStart) {
            $rateData = ['count' => 0, 'window_start' => $now];
        }
        
        $limit = ($action === 'comment') 
            ? self::RATE_LIMIT_COMMENTS_PER_MINUTE 
            : self::RATE_LIMIT_VOTES_PER_MINUTE;
        
        if ($rateData['count'] >= $limit) {
            Log::warning('BlogPortal: Rate limit exceeded', [
                'action' => $action,
                'visitor_id' => $visitorId,
                'count' => $rateData['count'],
                'limit' => $limit
            ]);
            return false;
        }
        
        $rateData['count']++;
        Session::put($sessionKey, $rateData);
        
        return true;
    }

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

    public function onChangeStatus(): array
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
        }

        throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
    }

    public function onVote(): array
    {
        if (!$this->checkRateLimit('vote')) {
            throw new ValidationException(['message' => 'Rate limit exceeded. Please wait a moment.']);
        }
        
        $comment = $this->validateAjaxMethod();

        if (empty($vote = input('vote')) || !in_array($vote, ['like', 'dislike'])) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
        }

        if ($this->config($vote . '_comment') !== '1') {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.disabled_method'));
        }

        if ($vote === 'like' && !$this->page['currentUserCanLike']) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to'));
        }

        if ($vote === 'dislike' && !$this->page['currentUserCanDislike']) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to'));
        }

        $success = match ($vote) {
            'like' => $comment->like(),
            'dislike' => $comment->dislike(),
            default => false,
        };

        if ($success) {
            return [
                'status' => 'success',
                'comment' => $this->renderPartial('@_single', [
                    'comment' => $comment
                ])
            ];
        }

        throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
    }

    public function onCreateReply(): array
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

    public function onCancelReply(): array
    {
        return [
            'submitText' => Lang::get('spanjaan.blogportal::lang.frontend.comments.submit_comment')
        ];
    }

    public function onReloadCaptcha(): array
    {
        $builder = (new \Gregwar\Captcha\CaptchaBuilder())->build();
        Session::put('blogportalCaptchaPhrase', $builder->getPhrase());

        return [
            'captchaImage' => $builder->inline()
        ];
    }

    public function onComment()
    {
        if (!$this->checkRateLimit('comment')) {
            throw new ValidationException(['message' => 'Rate limit exceeded. Please wait a moment before posting again.']);
        }
        
        if (empty($post = $this->getPost())) {
            throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_post'));
        }
        $this->prepareVars($post);

        if (!$this->verifyCsrfToken()) {
            throw new ValidationException([
                'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_csrf_token')
            ]);
        }

        $hasCaptcha = false;
        $hasHoneypot = false;
        if (!$this->verifyValidationCode(input('comment_validation'), input('comment_time'), $hasCaptcha, $hasHoneypot)) {
            throw new ValidationException([
                'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_validation_code')
            ]);
        }

        if ($hasCaptcha) {
            $sessionPhrase = Session::get('blogportalCaptchaPhrase');
            $inputPhrase = input('comment_captcha');
            
            if (empty($sessionPhrase) || empty($inputPhrase) || 
                strtolower($sessionPhrase) !== strtolower($inputPhrase)) {
                $builder = (new \Gregwar\Captcha\CaptchaBuilder())->build();
                Session::put('blogportalCaptchaPhrase', $builder->getPhrase());

                return Response::json([
                    'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.invalid_captcha'),
                    'captchaImage' => $builder->inline()
                ], 500);
            }
        }

        if ($hasHoneypot) {
            $honey = input('comment_honey');

            if (empty($honey) || !empty(input('comment_user')) || !empty(input('comment_email'))) {
                throw new ValidationException([
                    'message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.honeypot_filled')
                ]);
            }

            $honey = md5($honey);
        }

        if (!$this->page['currentUserCanComment']) {
            throw new ValidationException(['message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.not_allowed_to_comment')]);
        }

        if ($this->page['showCommentFormTos'] && input('comment_tos') !== '1') {
            throw new ValidationException(['message' => Lang::get('spanjaan.blogportal::lang.frontend.errors.tos_not_accepted')]);
        }

        $commentContent = input('comment_comment');
        if (empty(trim($commentContent))) {
            throw new ValidationException(['message' => 'Comment content is required.']);
        }

        $comment = new Comment([
            'status' => 'pending',
            'content' => $commentContent
        ]);

        if ($this->page['currentUser']) {
            $comment->authorable = $this->page['currentUser'];

            if ($this->config('moderate_user_comments') === '0' || $this->getBackendUser() !== null) {
                $comment->status = 'approved';
            }
        } else {
            $comment->author = isset($honey) ? input('comment_user' . $honey) : input('comment_user');
            $comment->author_email = isset($honey) ? input('comment_email' . $honey) : input('comment_email');
            $comment->author_uid = sha1(Request::ip() ?? 'unknown');
            $comment->authorable = Visitor::currentUser();

            if ($this->config('moderate_guest_comments') === '0') {
                $comment->status = 'approved';
            }
        }

        if ($this->config('form_comment_title')) {
            $title = input('comment_title');
            if (!empty(trim($title ?? ''))) {
                $comment->title = trim($title);
            }
        }

        $comment->post = $post;

        $parentId = input('comment_parent');
        if (!empty($parentId)) {
            $parentIdInt = intval($parentId);
            if ($parentIdInt > 0) {
                $parent = Comment::where('id', $parentIdInt)->first();
                if (empty($parent)) {
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
        }

        throw new ValidationException(Lang::get('spanjaan.blogportal::lang.frontend.errors.unknown_error'));
    }
}
