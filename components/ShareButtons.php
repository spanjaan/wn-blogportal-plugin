<?php namespace SpAnjaan\BlogPortal\Components;

use Cms\Classes\ComponentBase;
use SpAnjaan\BlogPortal\Models\Sharecount;
use Winter\Blog\Models\Post;

class ShareButtons extends ComponentBase
{   
    /**
     * The shareCounts method returns the share counts for the post.
     * 
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Share Buttons',
            'description' => 'Displays social media share buttons and tracks share counts.',
        ];
    }

    /**
     * The properties method returns the properties that the component allows.
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
     * The onRun method adds the required JS file to the page.
     * 
     * @return void
     */
    public function onRun()
    {
        $this->addJs('/plugins/spanjaan/blogportal/assets/js/share-button.js');
    }

    /**
     * The shareCounts method returns the share counts for the post.
     * 
     * @return array
     */
    public function shareCounts()
    {
        $postId = (int) $this->property('postId');
        $post = Post::find($postId);

        if (!$post) {
            return [
                'facebook' => 0,
                'twitter'  => 0,
                'linkedin' => 0,
                'whatsapp' => 0,
                'total'    => 0,
            ];
        }

        $sharecount = Sharecount::firstOrCreate(['post_id' => $post->id]);
        $totalShares = $this->getTotalShareCount($sharecount);

        return [
            'facebook' => $sharecount->facebook,
            'twitter'  => $sharecount->twitter,
            'linkedin' => $sharecount->linkedin,
            'whatsapp' => $sharecount->whatsapp,
            'total'    => $totalShares,
        ];
    }

    /**
     * The getTotalShareCount method returns the total share count for the post.
     * 
     * @param  Sharecount $sharecount
     * @return int
     */
    public function getTotalShareCount($sharecount)
    {
        return (int) $sharecount->facebook + (int) $sharecount->twitter + (int) $sharecount->linkedin + (int) $sharecount->whatsapp;
    }

    /**
     * The onShare method increments the share count for the specified platform.
     * 
     * @return array
     */
    public function onShare()
    {
        $platform = post('platform');
        $postId = post('postId');
    
        if (!$platform || !$postId) {
            throw new \ValidationException(['error' => 'Platform or Post ID is missing.']);
        }
    
        $shareCount = Sharecount::firstOrCreate(['post_id' => $postId]);
        $shareCount->incrementShareCount($platform);
    
        $totalShares = $this->getTotalShareCount($shareCount);
    
        return [
            'shareCounts' => array_merge($shareCount->toArray(), ['total' => $totalShares]),
        ];
    }
    
    /**
     * The onRender method adds the shareCounts and postId variables to the page.
     * 
     * @return void
     */
    public function onRender()
    {
        $this->page['shareCounts'] = $this->shareCounts();
        $this->page['postId'] = $this->property('postId');
    }
}
