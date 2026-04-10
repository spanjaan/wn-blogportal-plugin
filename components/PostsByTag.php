<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Winter\Blog\Components\Posts;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Models\Tag;

class PostsByTag extends Posts
{
    /**
     * The post list filtered by this tag model.
     *
     * @var Tag|array
     */
    public $tag = null;


    /**
     * Declare Component Details
     *
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'          => 'spanjaan.blogportal::lang.components.tag.label',
            'description'   => 'spanjaan.blogportal::lang.components.tag.comment'
        ];
    }

    /**
     * Component Properties
     *
     * @return void
     */
    public function defineProperties()
    {
        $properties = parent::defineProperties();
        $properties['tagFilter'] = [
            'title'         => 'spanjaan.blogportal::lang.components.tag.filter',
            'description'   => 'spanjaan.blogportal::lang.components.tag.filter_comment',
            'type'          => 'string',
            'default'       => '{{ :slug }}',
            'group'         => 'spanjaan.blogportal::lang.components.blogportal_group',
        ];
        return $properties;
    }

    /**
     * Run Component
     *
     * @return mixed
     */
    public function onRun()
    {
        $this->tag = $this->loadTag();

        if (empty($this->tag)) {
            $this->setStatusCode(404);
            return $this->controller->run('404');
        }

        // For sidebar active class
        $this->page['currentTagSlug'] = $this->tag->slug ?? null;

        // Pass the tag to page variable
        $this->page['tag'] = $this->tag;

        return parent::onRun();
    }

    /**
     * List Posts
     *
     * @return mixed
     */
    protected function listPosts()
    {
        $tag = $this->tag;
        $category = $this->category ? $this->category->id : null;
        $categorySlug = $this->category ? $this->category->slug : null;

        /*
         * List all the posts, eager load their categories
         */
        $isPublished = !parent::checkEditor();

        // Prepare Query
        $query = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags']);
        
        // Filter posts by the single selected tag
        $query->whereHas('spanjaan_blogportal_tags', function ($q) use ($tag) {
            return $q->where('spanjaan_blogportal_tags.id', $tag->id);
        });
        
        // Apply published filter
        if ($isPublished) {
            $query->where('published', '1');
        }

        // Execute query
        $posts = $query->listFrontEnd([
            'page'             => $this->property('pageNumber'),
            'sort'             => $this->property('sortOrder'),
            'perPage'          => $this->property('postsPerPage'),
            'search'           => trim(input('search') ?? ''),
            'category'         => $category,
            'published'        => $isPublished,
            'exceptPost'       => is_array($this->property('exceptPost'))
                ? $this->property('exceptPost')
                : preg_split('/,\s*/', $this->property('exceptPost'), -1, PREG_SPLIT_NO_EMPTY),
            'exceptCategories' => is_array($this->property('exceptCategories'))
                ? $this->property('exceptCategories')
                : preg_split('/,\s*/', $this->property('exceptCategories'), -1, PREG_SPLIT_NO_EMPTY),
        ]);

        /*
         * Add a "url" helper attribute for linking to each post and category
         */
        $posts->each(function ($post) use ($categorySlug) {
            $post->setUrl($this->postPage, $this->controller, ['category' => $categorySlug]);

            $post->categories->each(function ($category) {
                $category->setUrl($this->categoryPage, $this->controller);
            });
        });

        return $posts;
    }

    /**
     * Load Tag
     *
     * @return Tag|null
     */
    protected function loadTag()
    {
        if (!$slug = $this->property('tagFilter')) {
            return null;
        }
        // Single Tag Archive
        $tag = new Tag();

        $tag = $tag->isClassExtendedWith('Winter.Translate.Behaviors.TranslatableModel')
            ? $tag->transWhere('slug', $slug)
            : $tag->where('slug', $slug);

        $tag = $tag->first();
        return $tag ?: null;
    }
}
