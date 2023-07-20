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

        // Register Deprecated Methods
        $model->bindEvent('model.afterFetch', fn () => $this->registerDeprecatedMethods($model));
    }

    /**
     * Register deprecated methods
     *
     * @param Post $model
     * @return void
     */
    protected function registerDeprecatedMethods(Post $model)
    {
        $blogportal = $this->getBlogportalAttribute();

        // Dynamic Method - Receive Similar Posts from current Model
        $model->addDynamicMethod(
            'blogportal_similar_posts',
            fn ($limit = 3, $exclude = null) => $blogportal->getRelated($limit, $exclude)
        );

        // Dynamic Method - Receive Random Posts from current Model
        $model->addDynamicMethod(
            'blogportal_random_posts',
            fn ($limit = 3, $exclude = null) => $blogportal->getRandom($limit, $exclude)
        );

        // Dynamic Method - Get Next Post in the same category
        $model->addDynamicMethod(
            'blogportal_next_post_in_category',
            fn () => $blogportal->getNext(1, true)
        );

        // Dynamic Method - Get Previous Post in the same category
        $model->addDynamicMethod(
            'blogportal_prev_post_in_category',
            fn () => $blogportal->getPrevious(1, true)
        );

        // Dynamic Method - Get Next Post
        $model->addDynamicMethod(
            'blogportal_next_post',
            fn () => $blogportal->getNext()
        );

        // Dynamic Method - Get Previous Post
        $model->addDynamicMethod(
            'blogportal_prev_post',
            fn () => $blogportal->getPrevious()
        );
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

    /**
     * After Fetch Hook
     *
     * @return void
     */
    protected function afterFetch()
    {
        $tags = $this->model->spanjaan_blogportal_tags;
        if ($tags->count() === 0) {
            return;
        }

        /** @var Controller|null */
        $ctrl = Controller::getController();
        if ($ctrl instanceof Controller && !empty($ctrl->getLayout())) {
            $viewBag = $ctrl->getLayout()->getViewBag()->getProperties();

            // Set Tag URL
            if (isset($viewBag['blogportalTagPage'])) {
                $tags->each(fn ($tag) => $tag->setUrl($viewBag['blogportalTagPage'], $ctrl));
            }
        }
    }
}
