<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Log;
use Cms\Classes\ComponentBase;
use SpAnjaan\BlogPortal\Models\Sharecount;
use Winter\Blog\Models\Post;

class ShareButtons extends ComponentBase
{
    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_TWITTER = 'twitter';
    public const PLATFORM_LINKEDIN = 'linkedin';
    public const PLATFORM_WHATSAPP = 'whatsapp';

    protected const PLATFORMS = [
        self::PLATFORM_FACEBOOK,
        self::PLATFORM_TWITTER,
        self::PLATFORM_LINKEDIN,
        self::PLATFORM_WHATSAPP,
    ];

    /**
     * Define Component Details
     *
     * @return array
     */
    public function componentDetails(): array
    {
        return [
            'name'        => 'Share Buttons',
            'description' => 'Displays social media share buttons and tracks share counts.'
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
                'title'       => 'Post Slug Parameter',
                'description' => 'URL parameter name that holds the post slug.',
                'default'     => 'slug',
                'type'        => 'string',
            ],
        ];
    }

    /**
     * Run Component
     *
     * @return void
     */
    public function onRun(): void
    {
        $this->addJs('/plugins/spanjaan/blogportal/assets/js/share-button.js');
    }

    /**
     * Resolve Post ID from various sources
     *
     * @return int
     */
    public function resolvePostId(): int
    {
        $post = $this->page['post'] ?? null;
        if ($post instanceof Post && !empty($post->id)) {
            return (int) $post->id;
        }

        $slugParam = $this->property('postSlug', 'slug');
        $slug = $this->param($slugParam);
        if (!empty($slug)) {
            $found = Post::where('slug', $slug)->first();
            if ($found && !empty($found->id)) {
                return (int) $found->id;
            }
        }

        $id = (int) $this->param('id');
        if ($id > 0) {
            return $id;
        }

        return 0;
    }

    /**
     * Get Share Counts for Current Post
     *
     * @return array
     */
    public function shareCounts(): array
    {
        return $this->getShareCountsForPost($this->resolvePostId());
    }

    /**
     * Get Share Counts for Specific Post
     *
     * @param int $postId
     * @return array
     */
    protected function getShareCountsForPost(int $postId): array
    {
        if (!$postId) {
            return $this->defaultCounts();
        }

        try {
            $sharecount = Sharecount::where('post_id', $postId)->first();
        } catch (\Throwable $e) {
            Log::error('BlogPortal ShareButtons: Failed to load share counts', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return $this->defaultCounts();
        }
        
        if (!$sharecount) {
            return $this->defaultCounts();
        }

        return [
            self::PLATFORM_FACEBOOK => (int) ($sharecount->facebook ?? 0),
            self::PLATFORM_TWITTER  => (int) ($sharecount->twitter  ?? 0),
            self::PLATFORM_LINKEDIN => (int) ($sharecount->linkedin ?? 0),
            self::PLATFORM_WHATSAPP => (int) ($sharecount->whatsapp ?? 0),
            'total'                 => $this->getTotalShareCount($sharecount),
        ];
    }

    /**
     * Calculate Total Share Count
     *
     * @param Sharecount $sharecount
     * @return int
     */
    public function getTotalShareCount(Sharecount $sharecount): int
    {
        return (int) ($sharecount->facebook ?? 0)
             + (int) ($sharecount->twitter  ?? 0)
             + (int) ($sharecount->linkedin ?? 0)
             + (int) ($sharecount->whatsapp ?? 0);
    }

    /**
     * Handle Share Button Click
     *
     * @return array
     */
    public function onShare(): array
    {
        $platform = (string) post('platform');
        $postId   = (int)   post('postId');

        if (!in_array($platform, self::PLATFORMS, true)) {
            Log::warning('BlogPortal ShareButtons: Invalid platform attempted', [
                'platform' => $platform,
                'ip' => request()->ip() ?? 'unknown'
            ]);
            return ['error' => 'Invalid platform'];
        }

        if (!$postId || $postId <= 0) {
            return ['error' => 'Invalid post ID'];
        }

        try {
            $postExists = Post::where('id', $postId)->exists();
        } catch (\Throwable $e) {
            Log::error('BlogPortal ShareButtons: Database error checking post', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Post not found'];
        }
        
        if (!$postExists) {
            return ['error' => 'Post not found'];
        }

        try {
            $sharecount = Sharecount::where('post_id', $postId)->first();
            
            if (!$sharecount) {
                $sharecount = new Sharecount();
                $sharecount->post_id = $postId;
                $sharecount->facebook = 0;
                $sharecount->twitter = 0;
                $sharecount->linkedin = 0;
                $sharecount->whatsapp = 0;
            }
            
            $sharecount->incrementShareCount($platform);
        } catch (\Throwable $e) {
            Log::error('BlogPortal ShareButtons: Failed to increment share count', [
                'post_id' => $postId,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to update share count'];
        }

        return [
            'shareCounts' => [
                self::PLATFORM_FACEBOOK => (int) ($sharecount->facebook ?? 0),
                self::PLATFORM_TWITTER  => (int) ($sharecount->twitter  ?? 0),
                self::PLATFORM_LINKEDIN => (int) ($sharecount->linkedin ?? 0),
                self::PLATFORM_WHATSAPP => (int) ($sharecount->whatsapp ?? 0),
                'total'                 => $this->getTotalShareCount($sharecount),
            ],
        ];
    }

    /**
     * Get Default Share Counts
     *
     * @return array
     */
    protected function defaultCounts(): array
    {
        return [
            self::PLATFORM_FACEBOOK => 0,
            self::PLATFORM_TWITTER  => 0,
            self::PLATFORM_LINKEDIN => 0,
            self::PLATFORM_WHATSAPP => 0,
            'total'                 => 0,
        ];
    }
}
