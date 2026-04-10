<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Cms\Classes\ComponentBase;
use SpAnjaan\BlogPortal\Models\Sharecount;
use Winter\Blog\Models\Post;

class ShareButtons extends ComponentBase
{
    /**
     * Supported share platforms
     *
     * @var array
     */
    protected const PLATFORMS = [
        'facebook',
        'twitter',
        'linkedin',
        'whatsapp',
    ];

    /**
     * Define component details
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
     * Define component properties
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
     * @return mixed
     */
    public function onRun()
    {
        
        $this->addJs('/plugins/spanjaan/blogportal/assets/js/share-button.js');

    }

    /**
     * Resolve post ID by:
     * 1. page['post'] already set by Blog Post component (most common case)
     * 2. URL :slug param → DB lookup
     * 3. URL :id param (numeric) → direct
     *
     * Must be public so Twig can call {{ __SELF__.resolvePostId() }}
     *
     * @return int
     */
    public function resolvePostId(): int
    {
        // 1️⃣ Blog post component already resolved the post onto the page
        $post = $this->page['post'] ?? null;
        if ($post instanceof Post && $post->id) {
            return (int) $post->id;
        }

        // 2️⃣ Try slug URL parameter → DB lookup
        $slugParam = $this->property('postSlug', 'slug');
        $slug = $this->param($slugParam);
        if ($slug) {
            $found = Post::where('slug', $slug)->first();
            if ($found) {
                return (int) $found->id;
            }
        }

        // 3️⃣ Try numeric :id URL parameter
        $id = (int) $this->param('id');
        if ($id) {
            return $id;
        }

        return 0;
    }

    /**
     * Get the share counts for the current post.
     * Called via {{ __SELF__.shareCounts() }} in the component template.
     *
     * @return array
     */
    public function shareCounts(): array
    {
        return $this->getShareCountsForPost($this->resolvePostId());
    }

    /**
     * Get the share counts for a given post ID
     *
     * @param int $postId
     * @return array
     */
    protected function getShareCountsForPost(int $postId): array
    {
        if (!$postId) {
            return $this->defaultCounts();
        }

        $sharecount = Sharecount::where('post_id', $postId)->first();
        
        if (!$sharecount) {
            return $this->defaultCounts();
        }

        return [
            'facebook' => (int) ($sharecount->facebook ?? 0),
            'twitter'  => (int) ($sharecount->twitter  ?? 0),
            'linkedin' => (int) ($sharecount->linkedin ?? 0),
            'whatsapp' => (int) ($sharecount->whatsapp ?? 0),
            'total'    => $this->getTotalShareCount($sharecount),
        ];
    }

    /**
     * Calculate the total share count
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
     * Handle the share action and update the share count
     *
     * @return array
     */
    public function onShare(): array
    {
        $platform = (string) post('platform');
        $postId   = (int)   post('postId');

        if (!in_array($platform, self::PLATFORMS)) {
            return ['error' => 'Invalid platform'];
        }

        if (!$postId) {
            return ['error' => 'Invalid post ID'];
        }

        if (!Post::where('id', $postId)->exists()) {
            return ['error' => 'Post not found'];
        }

        $sharecount = Sharecount::where('post_id', $postId)->first();
        
        if (!$sharecount) {
            $sharecount = new Sharecount(['post_id' => $postId]);
        }
        
        $sharecount->incrementShareCount($platform);

        return [
            'shareCounts' => [
                'facebook' => (int) ($sharecount->facebook ?? 0),
                'twitter'  => (int) ($sharecount->twitter  ?? 0),
                'linkedin' => (int) ($sharecount->linkedin ?? 0),
                'whatsapp' => (int) ($sharecount->whatsapp ?? 0),
                'total'    => $this->getTotalShareCount($sharecount),
            ],
        ];
    }

    /**
     * Return default zero counts
     *
     * @return array
     */
    protected function defaultCounts(): array
    {
        return [
            'facebook' => 0,
            'twitter'  => 0,
            'linkedin' => 0,
            'whatsapp' => 0,
            'total'    => 0,
        ];
    }
}