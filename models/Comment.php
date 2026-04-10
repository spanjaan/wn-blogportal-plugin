<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Lang;
use Markdown;
use Model;
use SpAnjaan\BlogPortal\Models\BlogPortalSettings;
use SpAnjaan\BlogPortal\Models\Visitor;

/**
 * Comment Model
 */
class Comment extends Model
{
    /** Time interval constants (in seconds) */
    public const SECONDS_28_DAYS = 2419200;  // 28 days
    public const SECONDS_1_DAY = 86400;      // 1 day
    public const SECONDS_1_HOUR = 3600;     // 1 hour
    public const SECONDS_1_MINUTE = 60;      // 1 minute

    /**
     * Table associated with this Model
     *
     * @var string
     */
    public $table = 'spanjaan_blogportal_comments';

    /**
     * Enable Modal Timestamps
     *
     * @var boolean
     */
    public $timestamps = true;

    /**
     * Guarded Model attributes
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Fillable Model attributes
     *
     * @var array
     */
    protected $fillable = [
        "status",
        "title",
        "content",
        "favorite",
        "author",
        "author_email",
        "author_subscription",
        "parent_id",
        "author_id"
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'author'       => 'nullable|string|min:3',
        'author_email' => 'nullable|email',
        'status'       => 'required|in:pending,approved,rejected,spam',
        'content'      => 'required|string|min:3'
    ];

    /**
     * @var array appends attributes to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array hidden attributes removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * Mutable Date Attributes
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'approved_at',
        'rejected_at',
    ];

    /**
     * Define Belongs-To Relationships
     *
     * @var array
     */
    public $belongsTo = [
        'post'   => [
            \Winter\Blog\Models\Post::class,
            'key' => 'post_id'
        ],
        'parent' => Comment::class,
    ];

    /**
     * Define Has-Many Relationships
     *
     * @var array
     */
    public $hasMany = [
        'children' => [
            Comment::class,
            'key' => 'parent_id',
        ]
    ];

    /**
     * Define Morph-To Relationships
     *
     * @var array
     */
    public $morphTo = [
        'authorable' => [
            'id'   => 'author_id',
            'type' => 'author_table'
        ]
    ];

    /**
     * Get Status Options
     *
     * @return array
     */
    public function getStatusOptions()
    {
        return [
            'pending'  => Lang::get('spanjaan.blogportal::lang.model.comments.statusPending'),
            'approved' => Lang::get('spanjaan.blogportal::lang.model.comments.statusApproved'),
            'rejected' => Lang::get('spanjaan.blogportal::lang.model.comments.statusRejected'),
            'spam'     => Lang::get('spanjaan.blogportal::lang.model.comments.statusSpam')
        ];
    }

    /**
     * [HOOK] - Before Save Event Listener
     *
     * @return void
     */
    public function beforeSave()
    {
        if ($this->status === 'approved') {
            if (empty($this->approved_at)) {
                $this->approved_at = date('Y-m-d H:i:s');
            }
            if (!empty($this->rejected_at)) {
                $this->rejected_at = null;
            }
        }

        if ($this->status === 'rejected' || $this->status === 'spam') {
            if (empty($this->rejected_at)) {
                $this->rejected_at = date('Y-m-d H:i:s');
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
     * [SETTER] Comment Content
     *
     * @return void
     */
    public function setContentAttribute(string $content)
    {
        $this->attributes['content']      = $content;
        $this->attributes['content_html'] = Markdown::parse($content);
    }

    /**
     * [GETTER] Comment Content, depending on the set option
     *
     * @return string
     */
    public function getCommentContentAttribute(): string
    {
        if (BlogPortalSettings::get('form_comment_markdown', BlogPortalSettings::defaultValue('form_comment_markdown')) === '1') {
            return $this->content_html;
        } else {
            return $this->content;
        }
    }

    /**
     * [ACCESSOR] Default avatar (80px)
     * Usage: $comment->avatar
     */
    public function getAvatarAttribute(): string
    {
        return $this->getAvatar(80);
    }


    /**
     * Get avatar with custom size
     * Usage: $comment->getAvatar(120)
     *
     * @param int $size
     * @return string
     */
    public function getAvatar(int $size = 80): string
    {
        // 1️⃣ If author_email exists → Gravatar
        if (!empty($this->author_email)) {
            return $this->getGravatar($this->author_email, $size);
        }

        // 2️⃣ If author relation exists
        if ($this->author_id && $this->authorable) {

            // Backend User
            if ($this->author_table === 'Backend\Models\User') {
                if (method_exists($this->authorable, 'getAvatarThumb')) {
                    return $this->authorable->getAvatarThumb($size);
                }
            }

            // Frontend User (Winter User plugin)
            if (in_array(class_basename($this->author_table), ['User', 'UserModel'])) {

                // If avatar image exists
                if (!empty($this->authorable->avatar)) {
                    try {
                        return $this->authorable->avatar->getThumb(
                            $size,
                            $size,
                            ['mode' => 'crop']
                        );
                    } catch (\Exception $e) {
                        // fallback to gravatar below
                    }
                }

                // If no avatar → fallback to email gravatar
                if (!empty($this->authorable->email)) {
                    return $this->getGravatar($this->authorable->email, $size);
                }
            }

            // Other models fallback
            if (!empty($this->authorable->email)) {
                return $this->getGravatar($this->authorable->email, $size);
            }
        }

        // 3️⃣ Default fallback
        return $this->getGravatar('none', $size);
    }


    /**
     * Generate Gravatar URL
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
     * [GETTER] Author's display name, depending on the author type
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        // 1️⃣ If author manually entered a name (guest or custom)
        if (!empty($this->author)) {
            return $this->author;
        }

        // 2️⃣ If author_id exists
        if ($this->author_id && $this->authorable) {

            // Backend user (admin) — use BlogPortal behavior
            if ($this->author_table === 'Backend\Models\User') {
                return $this->authorable->blogportal->getDisplayName();
            }

            // Frontend user (Winter/User plugin)
            $frontendUser = $this->authorable;

            // Prefer 'name' field
            if (!empty($frontendUser->name)) {
                return $frontendUser->name;
            }

            // If only first_name / last_name exists
            if (!empty($frontendUser->first_name) || !empty($frontendUser->last_name)) {
                return trim(($frontendUser->first_name ?? '') . ' ' . ($frontendUser->last_name ?? ''));
            }

            // Fallback to username
            if (!empty($frontendUser->username)) {
                return $frontendUser->username;
            }

            // Fallback to email prefix
            if (!empty($frontendUser->email)) {
                return explode('@', $frontendUser->email)[0];
            }
        }

        // 3️⃣ Default guest
        return trans('spanjaan.blogportal::lang.model.comments.guest');
    }

    /**
     * [GETTER] Formatted published ago date/time.
     *
     * @return string
     */
    public function getPublishedAgoAttribute(): string
    {
        $seconds = (time() - $this->created_at->getTimestamp());

        if ($seconds >= self::SECONDS_28_DAYS) {
            return date('F, j. Y - H:i', $this->created_at->getTimestamp());
        } elseif ($seconds >= self::SECONDS_1_DAY) {
            $amount = intval($seconds / self::SECONDS_1_DAY);
            $format = 'days';
        } elseif ($seconds >= self::SECONDS_1_HOUR) {
            $amount = intval($seconds / self::SECONDS_1_HOUR);
            $format = 'hours';
        } elseif ($seconds >= self::SECONDS_1_MINUTE) {
            $amount = intval($seconds / self::SECONDS_1_MINUTE);
            $format = 'minutes';
        } else {
            return trans('spanjaan.blogportal::lang.model.comments.seconds_ago');
        }

        return trans('spanjaan.blogportal::lang.model.comments.x_ago', [
            'amount' => $amount,
            'format' => trans('spanjaan.blogportal::lang.model.post.published_format_' . $format)
        ]);
    }

    /**
     * [GETTER] Check if current user already liked this comment.
     *
     * @return boolean
     */
    public function getCurrentLikesAttribute(): bool
    {
        $visitor = Visitor::currentUser();
        return $visitor->getCommentVote($this->id) === 'like';
    }

    /**
     * [GETTER] Check if current user already disliked this comment.
     *
     * @return boolean
     */
    public function getCurrentDislikesAttribute(): bool
    {
        $visitor = Visitor::currentUser();
        return $visitor->getCommentVote($this->id) === 'dislike';
    }

    /**
     * [ACTION] Like a Comment
     *
     * @return bool
     */
    public function like(): bool
    {
        $visitor = Visitor::currentUser();

        $vote = $visitor->getCommentVote($this->id);
        if ($vote === 'like') {
            return true;
        } elseif ($vote === 'dislike') {
            if (!$visitor->removeCommentDislike($this->id)) {
                return false;
            }
            $this->dislikes = $this->dislikes - 1;
        }

        if ($visitor->addCommentLike($this->id)) {
            $this->likes = $this->likes + 1;
            return $this->save();
        } else {
            return false;
        }
    }

    /**
     * [ACTION] Dislike A Comment
     *
     * @return bool
     */
    public function dislike(): bool
    {
        $visitor = Visitor::currentUser();

        $vote = $visitor->getCommentVote($this->id);
        if ($vote === 'dislike') {
            return true;
        } elseif ($vote === 'like') {
            if (!$visitor->removeCommentLike($this->id)) {
                return false;
            }
            $this->likes = $this->likes - 1;
        }

        if ($visitor->addCommentDislike($this->id)) {
            $this->dislikes = $this->dislikes + 1;
            return $this->save();
        } else {
            return false;
        }
    }

    /**
     * [ACTION] Change comment status
     *
     * @param string $status
     * @return boolean
     */
    public function changeStatus(string $status): bool
    {
        $this->status      = $status;
        $this->approved_at = $status === 'approved' ? date("Y-m-d H:i:s") : null;
        $this->rejected_at = in_array($status, ['rejected', 'spam']) ? date("Y-m-d H:i:s") : null;
        return $this->save();
    }

    /**
     * [ACTION] Approve
     *
     * @return boolean
     */
    public function approve(): bool
    {
        return $this->changeStatus('approved');
    }

    /**
     * [ACTION] Reject Comment
     *
     * @return boolean
     */
    public function reject(): bool
    {
        return $this->changeStatus('rejected');
    }

    /**
     * [ACTION] Mark Comment as spam
     *
     * @return boolean
     */
    public function spam(): bool
    {
        return $this->changeStatus('spam');
    }

    /**
     * [ACTION] Pending Comment
     *
     * @return boolean
     */
    public function pending(): bool
    {
        return $this->changeStatus('pending');
    }
}