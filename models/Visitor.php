<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Log;
use Model;
use Request;
use Session;

class Visitor extends Model
{
    public const CLOUDLARE_SOLUTION_HEADER = 'HTTP_CF_VISITOR';
    public const CLOUDLARE_RAY_HEADER = 'HTTP_CF_RAY';
    public const CLOUDLARE_REAL_IP_HEADER = 'HTTP_CF_CONNECTING_IP';
    public const MAX_POSTS_ARRAY_SIZE = 10000;
    public const MAX_VOTES_ARRAY_SIZE = 5000;

    /** @var string|null */
    protected static ?string $cachedIp = null;

    /** @var string */
    public $table = 'spanjaan_blogportal_visitors';

    /** @var bool */
    public $timestamps = true;

    /** @var array<string> */
    protected $guarded = [];

    /** @var array<string> */
    protected $fillable = [
        'user',
    ];

    /** @var array<string> */
    protected $jsonable = [
        'posts',
        'likes',
        'dislikes',
    ];

    /**
     * Get Current Visitor User
     *
     * @return self
     */
    public static function currentUser(): self
    {
        $visitorId = self::getVisitorId();

        return self::firstOrCreate([
            'user' => $visitorId,
        ]);
    }

    /**
     * Generate Visitor ID
     *
     * @return string
     */
    protected static function getVisitorId(): string
    {
        $sessionId = self::getSessionId();
        if ($sessionId) {
            return 'session:' . $sessionId;
        }

        $ip = self::getClientIp();
        $agent = Request::header('User-Agent', 'unknown');

        return 'ip:' . hash_hmac('sha256', $ip, $agent);
    }

    /**
     * Get Session ID
     *
     * @return string|null
     */
    protected static function getSessionId(): ?string
    {
        if (class_exists('Session') && Session::driver()) {
            $sessionId = Session::getId();
            if (!empty($sessionId) && strlen($sessionId) > 8) {
                return $sessionId;
            }
        }
        return null;
    }

    /**
     * Get Client IP Address
     *
     * @return string
     */
    protected static function getClientIp(): string
    {
        if (self::$cachedIp !== null) {
            return self::$cachedIp;
        }

        $ip = self::getIpFromDirectSources();

        self::$cachedIp = $ip;

        return $ip;
    }

    /**
     * Get IP from Direct Sources
     *
     * @return string
     */
    protected static function getIpFromDirectSources(): string
    {
        if (self::validateCloudflareHeaders()) {
            $cfIp = self::getCloudflareIp(self::CLOUDLARE_REAL_IP_HEADER);
            if ($cfIp !== null) {
                return $cfIp;
            }
        }

        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            $value = Request::header($key) ?? $_SERVER[$key] ?? null;
            if (!empty($value)) {
                $ips = array_map('trim', explode(',', $value));
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                        return $ip;
                    }
                }
                $ip = trim(explode(',', $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        $fallbackIp = Request::ip();
        if ($fallbackIp && filter_var($fallbackIp, FILTER_VALIDATE_IP)) {
            return $fallbackIp;
        }

        return '127.0.0.1';
    }

    /**
     * Validate Cloudflare Headers
     *
     * @return bool
     */
    protected static function validateCloudflareHeaders(): bool
    {
        $cfRay = Request::header(self::CLOUDLARE_RAY_HEADER);
        return !empty($cfRay) && preg_match('/^[a-f0-9]{16}$/i', $cfRay);
    }

    /**
     * Get Cloudflare Real IP
     *
     * @param string $header
     * @return string|null
     */
    protected static function getCloudflareIp(string $header): ?string
    {
        $value = Request::header($header);
        if (empty($value)) {
            return null;
        }

        $parts = explode(',', $value);
        $ip = trim($parts[0]);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return null;
            }
        }

        return $ip;
    }

    /**
     * Check if Post has been Seen
     *
     * @param mixed $post
     * @return bool
     */
    public function hasSeen($post): bool
    {
        if ($post instanceof \Winter\Blog\Models\Post) {
            $post = $post->id;
        }
        $post = (int) $post;

        $posts = $this->getArrayAttribute('posts');

        return in_array($post, $posts, true);
    }

    /**
     * Mark Post as Seen
     *
     * @param mixed $post
     * @return bool
     */
    public function markAsSeen($post): bool
    {
        if ($post instanceof \Winter\Blog\Models\Post) {
            $post = $post->id;
        }
        $post = (int) $post;

        $posts = $this->getArrayAttribute('posts');

        if (count($posts) >= self::MAX_POSTS_ARRAY_SIZE) {
            $posts = array_slice($posts, -(int) ceil(self::MAX_POSTS_ARRAY_SIZE / 2));
        }

        if (!in_array($post, $posts, true)) {
            $posts[] = $post;
            $this->setArrayAttribute('posts', $posts);
            return $this->save();
        }

        return true;
    }

    /**
     * Get Comment Vote Status
     *
     * @param mixed $comment
     * @return string|null
     */
    public function getCommentVote($comment): ?string
    {
        if ($comment instanceof Comment) {
            $comment = $comment->id;
        }
        $comment = (int) $comment;

        $likes = $this->getArrayAttribute('likes');
        $dislikes = $this->getArrayAttribute('dislikes');

        if (in_array($comment, $likes, true)) {
            return 'like';
        }

        if (in_array($comment, $dislikes, true)) {
            return 'dislike';
        }

        return null;
    }

    /**
     * Add Like to Comment
     *
     * @param mixed $comment
     * @return bool
     */
    public function addCommentLike($comment): bool
    {
        if ($comment instanceof Comment) {
            $comment = $comment->id;
        }
        $comment = (int) $comment;

        $likes = $this->getArrayAttribute('likes');

        if (!in_array($comment, $likes, true)) {
            if (count($likes) >= self::MAX_VOTES_ARRAY_SIZE) {
                $this->removeCommentLike($likes[0]);
                $likes = $this->getArrayAttribute('likes');
            }
            $likes[] = $comment;
            $this->setArrayAttribute('likes', $likes);
            return $this->save();
        }

        return true;
    }

    /**
     * Remove Like from Comment
     *
     * @param mixed $comment
     * @return bool
     */
    public function removeCommentLike($comment): bool
    {
        if ($comment instanceof Comment) {
            $comment = $comment->id;
        }
        $comment = (int) $comment;

        return $this->removeFromArrayAttribute('likes', $comment);
    }

    /**
     * Add Dislike to Comment
     *
     * @param mixed $comment
     * @return bool
     */
    public function addCommentDislike($comment): bool
    {
        if ($comment instanceof Comment) {
            $comment = $comment->id;
        }
        $comment = (int) $comment;

        $dislikes = $this->getArrayAttribute('dislikes');

        if (!in_array($comment, $dislikes, true)) {
            if (count($dislikes) >= self::MAX_VOTES_ARRAY_SIZE) {
                $this->removeCommentDislike($dislikes[0]);
                $dislikes = $this->getArrayAttribute('dislikes');
            }
            $dislikes[] = $comment;
            $this->setArrayAttribute('dislikes', $dislikes);
            return $this->save();
        }

        return true;
    }

    /**
     * Remove Dislike from Comment
     *
     * @param mixed $comment
     * @return bool
     */
    public function removeCommentDislike($comment): bool
    {
        if ($comment instanceof Comment) {
            $comment = $comment->id;
        }
        $comment = (int) $comment;

        return $this->removeFromArrayAttribute('dislikes', $comment);
    }

    /**
     * Get Array Attribute
     *
     * @param string $key
     * @return array<int>
     */
    protected function getArrayAttribute(string $key): array
    {
        $value = $this->getAttribute($key);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn(mixed $v): bool => is_int($v)));
    }

    /**
     * Set Array Attribute
     *
     * @param string $key
     * @param array<int> $value
     * @return void
     */
    protected function setArrayAttribute(string $key, array $value): void
    {
        $cleaned = array_values(array_filter(array_unique($value), static fn(mixed $v): bool => is_int($v)));
        $this->setAttribute($key, $cleaned);
    }

    /**
     * Remove Value from Array Attribute
     *
     * @param string $key
     * @param int $value
     * @return bool
     */
    protected function removeFromArrayAttribute(string $key, int $value): bool
    {
        $array = $this->getArrayAttribute($key);
        $initialCount = count($array);

        $filtered = array_values(array_filter($array, fn($val) => $val !== $value));

        if (count($filtered) === $initialCount) {
            return true;
        }

        $this->setArrayAttribute($key, $filtered);
        return $this->save();
    }
}
