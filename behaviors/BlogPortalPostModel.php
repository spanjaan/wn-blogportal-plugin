<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Behaviors;

use Cms\Classes\Controller;
use Winter\Storm\Extension\ExtensionBase;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Classes\BlogPortalPost;
use SpAnjaan\BlogPortal\Models\Comment;
use SpAnjaan\BlogPortal\Models\Tag;

class BlogPortalPostModel extends ExtensionBase
{
    /**
     * Parent Post Model
     *
     * @var Post
     */
    protected Post $model;

    /**
     * BlogPortal Post Model DataSet
     *
     * @var ?BlogPortalPost
     */
    protected ?BlogPortalPost $blogportalSet;

    /**
     * Constructor
     *
     * @param Post $model
     */
    public function __construct(Post $model)
    {
        $this->model = $model;

        // Add Blog Comments
        $model->hasMany['spanjaan_blogportal_comments'] = [
            Comment::class
        ];

        $model->hasMany['spanjaan_blogportal_comments_count'] = [
            Comment::class,
            'count' => true
        ];

        // Add Blog Tags
        $model->belongsToMany['spanjaan_blogportal_tags'] = [
            Tag::class,
            'table' => 'spanjaan_blogportal_tags_posts',
            'order' => 'slug'
        ];

        // Register Tags Scope
        $model->addDynamicMethod('scopeFilterTags', function ($query, $tags) {
            return $query->whereHas('spanjaan_blogportal_tags', function ($q) use ($tags) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $tags);
            });
        });

    }


    /**
     * Get main BlogPortal Space
     *
     * @return BlogPortalPost
     */
    public function getBlogportalAttribute()
    {
        if (empty($this->blogportalSet)) {
            $this->blogportalSet = new BlogPortalPost($this->model);
        }
        return $this->blogportalSet;
    }

}
