<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Backend\Models\User as BackendUser;
use Winter\Blog\Components\Posts;
use Winter\Blog\Models\Post;

class PostsByAuthor extends Posts
{
    /**
     * The post list filtered by this author model.
     *
     * @var ?BackendUser
     */
    public $author = null;

    /**
     * Declare Component Details
     *
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'          => 'spanjaan.blogportal::lang.components.author.label',
            'description'   => 'spanjaan.blogportal::lang.components.author.comment'
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
        $properties['authorFilter'] = [
            'title'         => 'spanjaan.blogportal::lang.components.author.filter',
            'description'   => 'spanjaan.blogportal::lang.components.author.filter_comment',
            'type'          => 'string',
            'default'       => '{{ :slug }}',
            'group'         => 'spanjaan.blogportal::lang.components.blogportal_group',
        ];
        $properties['authorUseSlugOnly'] = [
            'title'         => 'spanjaan.blogportal::lang.components.author.author_slug_only',
            'description'   => 'spanjaan.blogportal::lang.components.author.author_slug_only_comment',
            'type'          => 'checkbox',
            'default'       => '0',
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
        $this->author = $this->page['author'] = $this->loadAuthor();

        if (empty($this->author)) {
            $this->setStatusCode(404);
            return $this->controller->run('404');
        }

        return parent::onRun();
    }

    /**
     * List Posts
     *
     * @return mixed
     */
    protected function listPosts()
    {
        $author = $this->author->id;
        $category = $this->category ? $this->category->id : null;
        $categorySlug = $this->category ? $this->category->slug : null;

        /*
         * List all the posts, eager load their categories
         */
        $isPublished = !parent::checkEditor();

        $posts = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->where('user_id', '=', $author)
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
     * Load Author.
     *
     * @return BackendUser|null
     */
    protected function loadAuthor()
    {
        if (!$slug = $this->property('authorFilter')) {
            return null;
        }

        if (($user = BackendUser::where('spanjaan_blogportal_author_slug', $slug)->first()) === null) {
            if ($this->property('authorUseSlugOnly') === '0') {
                if (($user = BackendUser::where('login', $slug)->first()) === null) {
                    return null;
                }

                if (!empty($user->spanjaan_blogportal_author_slug)) {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $user;
    }
}
