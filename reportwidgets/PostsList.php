<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use Cms\Classes\Controller;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Lang;
use Winter\Blog\Models\Post;

class PostsList extends ReportWidgetBase
{
    /**
     * Initialize Widget
     *
     * @return void
     */
    public function init(): void
    {
    }

    /**
     * Define Widget Properties
     *
     * @return array
     */
    public function defineProperties(): array
    {
        return [
            'postPage' => [
                'title'       => 'winter.blog::lang.settings.posts_post',
                'description' => 'winter.blog::lang.settings.posts_post_description',
                'type'        => 'dropdown',
                'default'     => 'blog/post',
            ],
            'defaultOrder' => [
                'title'       => 'spanjaan.blogportal::lang.components.post.default_order',
                'description' => 'spanjaan.blogportal::lang.components.post.default_order_comment',
                'type'        => 'dropdown',
                'default'     => 'by_published',
            ],
        ];
    }

    /**
     * Get Post Page Options
     *
     * @return array
     */
    public function getPostPageOptions(): array
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Get Default Order Options
     *
     * @return array
     */
    public function getDefaultOrderOptions(): array
    {
        return [
            'by_published' => Lang::get('spanjaan.blogportal::lang.components.post.by_published'),
            'by_views'     => Lang::get('spanjaan.blogportal::lang.components.post.by_views'),
            'by_visitors'  => Lang::get('spanjaan.blogportal::lang.components.post.by_visitors'),
        ];
    }

    /**
     * Load Widget Assets
     *
     * @return void
     */
    protected function loadAssets(): void
    {
        $this->addCss('css/post-list.css');
    }

    /**
     * Get Posts Based on Order
     *
     * @param string $order
     * @return \Winter\Storm\Database\Collection
     */
    protected function getPosts(string $order): \Winter\Storm\Database\Collection
    {
        $query = Post::limit(10);

        $posts = match ($order) {
            'by_published' => $query->where('published', '1')
                ->orderBy('published_at', 'DESC')
                ->get(),
            'by_views' => $query->where('published', '1')
                ->orderBy('spanjaan_blogportal_views', 'DESC')
                ->orderBy('published_at', 'DESC')
                ->get(),
            'by_visitors' => $query->where('published', '1')
                ->orderBy('spanjaan_blogportal_unique_views', 'DESC')
                ->orderBy('published_at', 'DESC')
                ->get(),
            default => $query->get(),
        };

        if (!empty($postPage = $this->property('postPage'))) {
            $posts->each(fn($item) => $item->setUrl($postPage, new Controller(Theme::getActiveTheme())));
        }

        return $posts;
    }

    /**
     * Render Widget
     *
     * @return string
     */
    public function render(): string
    {
        $order = $this->property('defaultOrder', 'by_published');
        if (!array_key_exists($order, $this->getDefaultOrderOptions())) {
            $order = 'by_published';
        }

        $posts = $this->getPosts($order);

        return $this->makePartial('widget', [
            'order'         => $order,
            'posts'         => $posts,
            'postsPartial'  => $this->makePartial('posts', [
                'posts' => $posts,
            ]),
            'counts'        => [
                'total'     => Post::count(),
                'published' => Post::where('published', 1)->where('published_at', '<=', date('Y-m-d H:i:s'))->count(),
                'scheduled' => Post::where('published', 1)->where('published_at', '>', date('Y-m-d H:i:s'))->count(),
                'draft'     => Post::where('published', 0)->count(),
            ],
        ]);
    }

    /**
     * Change Post List AJAX Handler
     *
     * @return array
     */
    public function onChangePostList(): array
    {
        $order = input('order');
        if (!array_key_exists($order, $this->getDefaultOrderOptions())) {
            $order = 'by_published';
        }

        $posts = $this->getPosts($order);

        return [
            'order'       => $order,
            '#postsList' => $this->makePartial('posts', [
                'posts' => $posts,
            ]),
        ];
    }
}
