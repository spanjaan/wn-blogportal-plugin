<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Winter\Blog\Components\Posts;
use Winter\Blog\Models\Post;

class PopularPosts extends Posts
{
    /**
     * A collection of popular posts to display
     *
     * @var \Illuminate\Support\Collection
     */
    public $popularPosts;

    /**
     * Component Details
     *
     * @return array
     */
    public function componentDetails(): array
    {
        return [
            'name'          => 'spanjaan.blogportal::lang.components.popularPosts.label',
            'description'   => 'spanjaan.blogportal::lang.components.popularPosts.description'
        ];
    }

    /**
     * Run Component
     *
     * @return mixed
     */
    public function onRun()
    {
        $this->popularPosts = $this->loadPopularPosts();

        return parent::onRun();
    }

    /**
     * List Posts
     *
     * @return mixed
     */
    protected function listPosts()
    {
        $category = $this->category ? $this->category->id : null;
        $categorySlug = $this->category ? $this->category->slug : null;

        /*
         * List all the posts, eager load their categories
         */
        $isPublished = !parent::checkEditor();

        $posts = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->listFrontEnd([
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
     * Load popular posts
     *
     * @return mixed
     */
    protected function loadPopularPosts()
    {
        $popularPosts = Post::orderBy('spanjaan_blogportal_views', 'desc')->get();

        return $popularPosts;
    }
}

