<?php namespace SpAnjaan\BlogPortal\Components;

use Cms\Classes\ComponentBase;
use SpAnjaan\BlogPortal\Models\Sharecount;
use Winter\Blog\Models\Post;

class ShareButtons extends ComponentBase
{
    /**
     * Define component details
     * 
     * @return array
     */
    public function componentDetails()
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
    public function defineProperties()
    {
        return [
            'postId' => [
                'title'       => 'Post ID',
                'description' => 'The ID of the blog post to track shares for.',
                'default'     => '{{ :id }}',
                'type'        => 'string',
            ],
        ];
    }

    /**
     * Get the share counts for the specified post
     * 
     * @return array
     */
    public function shareCounts()
    {
        $postId = (int) $this->property('postId');

        // If no post ID is provided, get the first post's ID
        if (!$postId && $post = Post::first()) {
            $postId = $post->id;
        }

        // If no post ID is found, return default values
        if (!$postId) {
            return [
                'facebook' => 0,
                'twitter' => 0,
                'linkedin' => 0,
                'whatsapp' => 0,
                'total' => 0,
            ];
        }

        // Get or create the Sharecount record for the post
        $sharecount = Sharecount::firstOrCreate(['post_id' => $postId]);
        $totalShares = $this->getTotalShareCount($sharecount);

        return [
            'facebook' => $sharecount->facebook,
            'twitter' => $sharecount->twitter,
            'linkedin' => $sharecount->linkedin,
            'whatsapp' => $sharecount->whatsapp,
            'total' => $totalShares,
        ];
    }

    /**
     * Calculate the total share count
     * 
     * @param Sharecount $sharecount
     * @return int
     */
    public function getTotalShareCount($sharecount)
    {
        return $sharecount->facebook + $sharecount->twitter + $sharecount->linkedin + $sharecount->whatsapp;
    }

    /**
     * Handle the share action and update the share count
     * 
     * @return array
     */
    public function onShare()
    {
        $platform = post('platform');
        $postId = post('postId');

        // If no post ID is provided, get the first post's ID
        if (!$postId || $postId == 0) {
            $postId = Post::first()->id;
        }

        // Get or create the Sharecount record for the post
        $shareCount = Sharecount::firstOrCreate(['post_id' => $postId]);
        $shareCount->incrementShareCount($platform);

        // Calculate the updated total share count
        $totalShares = $this->getTotalShareCount($shareCount);

        return [
            'shareCounts' => array_merge($shareCount->toArray(), ['total' => $totalShares]),
        ];
    }

    /**
     * Prepare data for rendering
     */
    public function onRender()
    {
        $this->page['shareCounts'] = $this->shareCounts();
        $this->page['postId'] = $this->property('postId');
    }
}
