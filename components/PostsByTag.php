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
     * The multiple tags mode.
     *
     * @var ?string
     */
    public $tagsMode = null;

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
        $properties['tagAllowMultiple'] = [
            'title'         => 'spanjaan.blogportal::lang.components.tag.tag_multiple',
            'description'   => 'spanjaan.blogportal::lang.components.tag.tag_multiple_comment',
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
        $this->tag = $this->loadTag();

        if (is_array($this->tag)) {
            $this->page['tags'] = $this->tag;
        } else {
            $this->page['tag'] = $this->tag;
        }

        if (empty($this->tag)) {
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
        $tags = $this->tag;
        $tagsMode = $this->tagsMode;
        $category = $this->category ? $this->category->id : null;
        $categorySlug = $this->category ? $this->category->slug : null;

        /*
         * List all the posts, eager load their categories
         */
        $isPublished = !parent::checkEditor();

        // Prepare Query
        $query = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags']);
        if ($tagsMode === 'and') {
            $ids = array_map(fn ($item) => $item->id, $tags);

            $query->join('spanjaan_blogportal_tags_posts', 'winter_blog_posts.id', '=', 'spanjaan_blogportal_tags_posts.post_id')
                ->whereIn('spanjaan_blogportal_tags_posts.tag_id', array_map(fn ($item) => $item->id, $tags))
                ->groupBy('winter_blog_posts.id')
                ->havingRaw('count(DISTINCT "spanjaan_blogportal_tags_posts"."tag_id") = ?', [count($ids)]);
        } else {
            $query->whereHas('spanjaan_blogportal_tags', function ($query) use ($tags) {
                if (is_array($tags)) {
                    return $query->whereIn('spanjaan_blogportal_tags.id', array_map(fn ($item) => $item->id, $tags));
                } else {
                    return $query->where('spanjaan_blogportal_tags.id', $tags->id);
                }
            });
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
     * @return Tag|Tag[][]|null
     */
    protected function loadTag()
    {
        if (!$slug = $this->property('tagFilter')) {
            return null;
        }

        // Multiple Tag Archive
        if ($this->property('tagAllowMultiple') === '1') {
            //$tagsList = preg_split('/(\+|\,)/', $slug, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            if (strpos($slug, '+') !== false) {
                $this->tagsMode = 'and';
                $tagsList = explode('+', $slug);
            } elseif (strpos($slug, ',') !== false) {
                $this->tagsMode = 'or';
                $tagsList = explode(',', $slug);
            }

            if (isset($tagsList) && count($tagsList) > 1) {
                return Tag::whereIn('slug', $tagsList)->get()->all();
            }
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
