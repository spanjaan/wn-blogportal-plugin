<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Behaviors;

use SpAnjaan\BlogPortal\Classes\BlogPortalPost;
use SpAnjaan\BlogPortal\Models\Comment;
use SpAnjaan\BlogPortal\Models\Tag;
use Winter\Blog\Models\Post;
use Winter\Storm\Extension\ExtensionBase;

class BlogPortalPostModel extends ExtensionBase
{
    /** @var Post */
    protected Post $model;

    /** @var BlogPortalPost|null */
    protected ?BlogPortalPost $blogportalSet = null;

    /**
     * Constructor
     *
     * @param Post $model
     * @return void
     */
    public function __construct(Post $model)
    {
        $this->model = $model;

        $model->hasMany['spanjaan_blogportal_comments'] = [
            Comment::class,
        ];

        $model->hasMany['spanjaan_blogportal_comments_count'] = [
            Comment::class,
            'count' => true,
        ];

        $model->belongsToMany['spanjaan_blogportal_tags'] = [
            Tag::class,
            'table' => 'spanjaan_blogportal_tags_posts',
            'order' => 'slug',
        ];

        $model->addDynamicMethod(
            'scopeFilterTags',
            function ($query, $tags) {
                return $query->whereHas('spanjaan_blogportal_tags', function ($q) use ($tags) {
                    $q->whereIn('id', $tags);
                });
            }
        );
    }

    /**
     * Get BlogPortal Attribute
     *
     * @return BlogPortalPost
     */
    public function getBlogportalAttribute(): BlogPortalPost
    {
        if ($this->blogportalSet === null) {
            $this->blogportalSet = new BlogPortalPost($this->model);
        }
        return $this->blogportalSet;
    }
}
