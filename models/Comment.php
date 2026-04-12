<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Html;
use Lang;
use Log;
use Markdown;
use Model;

class Comment extends Model
{
    public const SECONDS_28_DAYS = 2419200;
    public const SECONDS_1_DAY = 86400;
    public const SECONDS_1_HOUR = 3600;
    public const SECONDS_1_MINUTE = 60;

    public const GRAVATAR_SIZE_DEFAULT = 80;
    public const GRAVATAR_SIZE_MIN = 1;
    public const GRAVATAR_SIZE_MAX = 2048;
    public const GRAVATAR_DEFAULT_EMAIL = 'none';

    /** @var string */
    public $table = 'spanjaan_blogportal_comments';

    /** @var bool */
    public $timestamps = true;

    /** @var array<string> */
    protected $guarded = [];

    /** @var array<string> */
    protected $fillable = [
        'status',
        'title',
        'content',
        'favorite',
        'author',
        'author_email',
        'author_subscription',
        'parent_id',
        'author_id',
    ];

    /** @var array<string> */
    public $rules = [
        'author'       => 'nullable|string|min:3',
        'author_email' => 'nullable|email',
        'status'       => 'required|in:pending,approved,rejected,spam',
        'content'      => 'required|string|min:3',
    ];

    /** @var array<string> */
    protected $appends = [];

    /** @var array<string> */
    protected $hidden = [];

    /** @var array<string> */
    protected $dates = [
        'created_at',
        'updated_at',
        'approved_at',
        'rejected_at',
    ];

    /** @var array<string, array<string, mixed>> */
    public $belongsTo = [
        'post'   => [
            \Winter\Blog\Models\Post::class,
            'key' => 'post_id',
        ],
        'parent' => Comment::class,
    ];

    /** @var array<string, array<string, mixed>> */
    public $hasMany = [
        'children' => [
            Comment::class,
            'key' => 'parent_id',
        ],
    ];

    /** @var array<string, array<string, mixed>> */
    public $morphTo = [
        'authorable' => [
            'id'   => 'author_id',
            'type' => 'author_table',
        ],
    ];

    /**
     * Get Status Options for Dropdown
     *
     * @return array
     */
    public function getStatusOptions(): array
    {
        return [
            'pending'  => Lang::get('spanjaan.blogportal::lang.model.comments.statusPending'),
            'approved' => Lang::get('spanjaan.blogportal::lang.model.comments.statusApproved'),
            'rejected' => Lang::get('spanjaan.blogportal::lang.model.comments.statusRejected'),
            'spam'     => Lang::get('spanjaan.blogportal::lang.model.comments.statusSpam'),
        ];
    }

    /**
     * Before Save Event Handler
     *
     * @return void
     */
    public function beforeSave(): void
    {
        if ($this->status === 'approved') {
            if (empty($this->approved_at)) {
                $this->approved_at = now();
            }
            if (!empty($this->rejected_at)) {
                $this->rejected_at = null;
            }
        }

        if ($this->status === 'rejected' || $this->status === 'spam') {
            if (empty($this->rejected_at)) {
                $this->rejected_at = now();
            }
            if (!empty($this->approved_at)) {
                $this->approved_at = null;
            }
        }

        if ($this->status === 'pending') {
            $this->approved_at = null;
            $this->rejected_at = null;
        }
    }

    /**
     * Set Content Attribute with Markdown Parsing
     *
     * @param string $content
     * @return void
     */
    public function setContentAttribute(string $content): void
    {
        $this->attributes['content'] = $content;
        $this->attributes['content_html'] = $this->sanitizeMarkdown(Markdown::parse($content));
    }

    /**
     * Sanitize Markdown HTML Output
     *
     * @param string $html
     * @return string
     */
    protected function sanitizeMarkdown(string $html): string
    {
        if (class_exists(\HTMLPurifier::class)) {
            try {
                $config = \HTMLPurifier_Config::create([
                    'CSS.AllowedProperties'        => '',
                    'AutoFormat.AutoParagraph'    => false,
                    'URI.AllowedSchemes'           => ['http', 'https', 'mailto'],
                    'HTML.ForbiddenElements'       => ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input'],
                ]);
                $purifier = new \HTMLPurifier($config);
                $html = $purifier->purify($html);
            } catch (\Throwable $e) {
                Log::warning('BlogPortal: HTMLPurifier failed, using fallback sanitization', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $html = preg_replace([
            '/\son\w+\s*=/i',
            '/\sstyle\s*=/i',
        ], ' data-blocked="1"', $html);

        $html = preg_replace_callback(
            '/<a\s+([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];

                if (preg_match('/href\s*=\s*["\']([^"\']*)["\']/i', $attrs, $hrefMatch)) {
                    $url = $hrefMatch[1];
                    if (!preg_match('/^(https?:|mailto:|#|\/)/i', $url)) {
                        return '<a data-blocked="1" target="_blank" rel="noopener noreferrer">';
                    }
                }

                if (!preg_match('/target\s*=/i', $attrs)) {
                    $attrs .= ' target="_blank" rel="noopener noreferrer"';
                } else {
                    $attrs = preg_replace(
                        '/target\s*=\s*["\'][^"\']*["\']/i',
                        'target="_blank" rel="noopener noreferrer"',
                        $attrs
                    );
                }

                $attrs = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
                $attrs = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $attrs);

                return '<a ' . trim($attrs) . '>';
            },
            $html
        );

        $html = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];

                $attrs = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
                $attrs = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $attrs);

                if (preg_match('/src\s*=\s*["\']([^"\']*)["\']/i', $attrs, $srcMatch)) {
                    $url = $srcMatch[1];
                    if (!preg_match('/^(https?:|\/)/i', $url)) {
                        return '<img data-blocked="1" alt="blocked image">';
                    }
                }

                if (!preg_match('/alt\s*=/i', $attrs)) {
                    $attrs .= ' alt=""';
                }

                return '<img ' . trim($attrs) . '>';
            },
            $html
        );

        $forbiddenTags = [
            'script', 'style', 'iframe', 'object', 'embed',
            'form', 'input', 'button', 'select', 'textarea',
        ];
        foreach ($forbiddenTags as $tag) {
            $html = preg_replace('/<' . $tag . '[^>]*>.*?<\/' . $tag . '>/is', '', $html);
            $html = preg_replace('/<' . $tag . '[^>]*\/>/is', '', $html);
        }

        return $html;
    }

    /**
     * Get Comment Content (HTML or Plain)
     *
     * @return string
     */
    public function getCommentContentAttribute(): string
    {
        if (BlogPortalSettings::get('form_comment_markdown', BlogPortalSettings::defaultValue('form_comment_markdown')) === '1') {
            return $this->content_html ?? '';
        }

        return $this->content ?? '';
    }

    /**
     * Get Avatar URL
     *
     * @return string
     */
    public function getAvatarAttribute(): string
    {
        return $this->getAvatar(self::GRAVATAR_SIZE_DEFAULT);
    }

    /**
     * Get Avatar URL with Custom Size
     *
     * @param int $size
     * @return string
     */
    public function getAvatar(int $size = self::GRAVATAR_SIZE_DEFAULT): string
    {
        $size = max(self::GRAVATAR_SIZE_MIN, min($size, self::GRAVATAR_SIZE_MAX));

        if (!empty($this->author_email)) {
            return $this->getGravatar($this->author_email, $size);
        }

        if ($this->author_id && $this->authorable) {
            if ($this->author_table === 'Backend\Models\User') {
                if (method_exists($this->authorable, 'getAvatarThumb')) {
                    return $this->authorable->getAvatarThumb($size);
                }
            }

            if (in_array(class_basename($this->author_table), ['User', 'UserModel'], true)) {
if (!empty($this->authorable->avatar)) {
                try {
                    return $this->authorable->avatar->getThumb(
                        $size,
                        $size,
                        ['mode' => 'crop']
                    );
                } catch (\Throwable $e) {
                    Log::warning('BlogPortal: Failed to get avatar thumb', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

                if (!empty($this->authorable->email)) {
                    return $this->getGravatar($this->authorable->email, $size);
                }
            }

            if (!empty($this->authorable->email)) {
                return $this->getGravatar($this->authorable->email, $size);
            }
        }

        return $this->getGravatar(self::GRAVATAR_DEFAULT_EMAIL, $size);
    }

    /**
     * Get Gravatar URL
     *
     * @param string $email
     * @param int $size
     * @return string
     */
    protected function getGravatar(string $email, int $size): string
    {
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
    }

    /**
     * Get Display Name Attribute
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->author)) {
            return e($this->author);
        }

        if ($this->author_id && $this->authorable) {
            if ($this->author_table === 'Backend\Models\User') {
                return $this->authorable->blogportal->getDisplayName();
            }

            $frontendUser = $this->authorable;

            if (!empty($frontendUser->name)) {
                return e($frontendUser->name);
            }

            if (!empty($frontendUser->first_name) || !empty($frontendUser->last_name)) {
                return e(trim(($frontendUser->first_name ?? '') . ' ' . ($frontendUser->last_name ?? '')));
            }

            if (!empty($frontendUser->username)) {
                return e($frontendUser->username);
            }

            if (!empty($frontendUser->email)) {
                $parts = explode('@', $frontendUser->email);
                return e($parts[0]);
            }
        }

        return e(trans('spanjaan.blogportal::lang.model.comments.guest'));
    }

    /**
     * Get Published Ago Attribute
     *
     * @return string
     */
    public function getPublishedAgoAttribute(): string
    {
        $seconds = (time() - $this->created_at->getTimestamp());

        if ($seconds >= self::SECONDS_28_DAYS) {
            return date('F, j. Y - H:i', $this->created_at->getTimestamp());
        }

        if ($seconds >= self::SECONDS_1_DAY) {
            $amount = intdiv($seconds, self::SECONDS_1_DAY);
            $format = 'days';
        } elseif ($seconds >= self::SECONDS_1_HOUR) {
            $amount = intdiv($seconds, self::SECONDS_1_HOUR);
            $format = 'hours';
        } elseif ($seconds >= self::SECONDS_1_MINUTE) {
            $amount = intdiv($seconds, self::SECONDS_1_MINUTE);
            $format = 'minutes';
        } else {
            return trans('spanjaan.blogportal::lang.model.comments.seconds_ago');
        }

        return trans('spanjaan.blogportal::lang.model.post.published_format_' . $format, [
            'amount' => $amount,
        ]);
    }

    /**
     * Check if Current User has Liked
     *
     * @return bool
     */
    public function getCurrentLikesAttribute(): bool
    {
        $visitor = Visitor::currentUser();
        return $visitor->getCommentVote($this->id) === 'like';
    }

    /**
     * Check if Current User has Disliked
     *
     * @return bool
     */
    public function getCurrentDislikesAttribute(): bool
    {
        $visitor = Visitor::currentUser();
        return $visitor->getCommentVote($this->id) === 'dislike';
    }

    /**
     * Like this Comment
     *
     * @return bool
     */
    public function like(): bool
    {
        $visitor = Visitor::currentUser();

        $vote = $visitor->getCommentVote($this->id);
        if ($vote === 'like') {
            return true;
        }

        if ($vote === 'dislike') {
            if (!$visitor->removeCommentDislike($this->id)) {
                return false;
            }
            $this->dislikes = ((int) $this->dislikes) - 1;
        }

        if ($visitor->addCommentLike($this->id)) {
            $this->likes = ((int) $this->likes) + 1;
            return $this->save();
        }

        return false;
    }

    /**
     * Dislike this Comment
     *
     * @return bool
     */
    public function dislike(): bool
    {
        $visitor = Visitor::currentUser();

        $vote = $visitor->getCommentVote($this->id);
        if ($vote === 'dislike') {
            return true;
        }

        if ($vote === 'like') {
            if (!$visitor->removeCommentLike($this->id)) {
                return false;
            }
            $this->likes = ((int) $this->likes) - 1;
        }

        if ($visitor->addCommentDislike($this->id)) {
            $this->dislikes = ((int) $this->dislikes) + 1;
            return $this->save();
        }

        return false;
    }

    /**
     * Change Comment Status
     *
     * @param string $status
     * @return bool
     */
    public function changeStatus(string $status): bool
    {
        $this->status      = $status;
        $this->approved_at = $status === 'approved' ? now() : null;
        $this->rejected_at = in_array($status, ['rejected', 'spam'], true) ? now() : null;
        return $this->save();
    }

    /**
     * Approve this Comment
     *
     * @return bool
     */
    public function approve(): bool
    {
        return $this->changeStatus('approved');
    }

    /**
     * Reject this Comment
     *
     * @return bool
     */
    public function reject(): bool
    {
        return $this->changeStatus('rejected');
    }

    /**
     * Mark as Spam
     *
     * @return bool
     */
    public function spam(): bool
    {
        return $this->changeStatus('spam');
    }

    /**
     * Set to Pending Status
     *
     * @return bool
     */
    public function pending(): bool
    {
        return $this->changeStatus('pending');
    }
}
