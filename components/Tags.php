<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use SpAnjaan\BlogPortal\Models\Tag as TagModel;

class Tags extends ComponentBase
{
    /**
     * A collection of tags to display
     *
     * @var Collection
     */
    public $tags;

    /**
     * @var string Reference to the page name for linking to categories.
     */
    public $tagPage;

    /**
     * @var string Slug of the currently selected tag, if any.
     */
    public $currentTagSlug;

    /**
     * Component Details
     *
     * @return void
     */
    public function componentDetails()
    {
        return [
            'name'          => 'spanjaan.blogportal::lang.components.tags.label',
            'description'   => 'spanjaan.blogportal::lang.components.tags.comment'
        ];
    }

    /**
     * Component Properties
     *
     * @return void
     */
    public function defineProperties()
    {
        return [
            'slug' => [
                'title'             => 'winter.blog::lang.settings.post_slug',
                'description'       => 'winter.blog::lang.settings.post_slug_description',
                'default'           => '{{ :slug }}',
                'type'              => 'string',
            ],
            'tagPage' => [
                'title'             => 'spanjaan.blogportal::lang.components.tags.tags_page',
                'description'       => 'spanjaan.blogportal::lang.components.tags.tags_page_comment',
                'type'              => 'string',
                'default'           => 'blog/tag',

            ],
            'onlyPromoted' => [
                'title'             => 'spanjaan.blogportal::lang.components.tags.only_promoted',
                'description'       => 'spanjaan.blogportal::lang.components.tags.only_promoted_comment',
                'type'              => 'checkbox',
                'default'           => '0'
            ],
            'hideEmpty' => [
                'title'             => 'spanjaan.blogportal::lang.components.tags.hide_empty',
                'description'       => 'spanjaan.blogportal::lang.components.tags.hide_empty_comment',
                'type'              => 'checkbox',
                'default'           => '1'
            ],
        ];
    }

    /**
     * Get Tag Page Option
     *
     * @return void
     */
    public function getTagPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Run
     *
     * @return void
     */
    public function onRun()
    {
        $this->currentTagSlug = $this->page['currentTagSlug'] = $this->property('slug');
        $this->tagPage = $this->page['tagPage'] = $this->property('tagPage');
        $this->tags = $this->page['tags'] = $this->listTags();
    }

    /**
     * Load popular tags
     *
     * @return mixed
     */
    protected function listTags()
    {
        $query = TagModel::withCount('posts')
            ->orderBy('posts_count', 'desc');

        if ($this->property('hideEmpty') === '1') {
            $query->having('posts_count', '>', 0);
        }

        if ($this->property('onlyPromoted') === '1') {
            $query->where('promote', '1');
        }

        return $query->get()->each(function($tag) {
            $tag->setUrl($this->tagPage, $this->controller);
            // Strip any trailing page number (e.g. /2, /3)
            $tag->url = preg_replace('#/\d+$#', '', $tag->url);
        });
    }
}
