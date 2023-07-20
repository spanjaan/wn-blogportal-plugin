<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Classes;

use Cms\Classes\Controller;
use Illuminate\Support\Collection;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Models\Visitor;

class BlogPortalPost
{
    /**
     * Post Model
     *
     * @var Post
     */
    protected Post $model;

    /**
     * Post Tag Collection
     *
     * @var ?Collection
     */
    protected ?Collection $tagCollection;

    /**
     * Post Promoted Tag Collection
     *
     * @var ?Collection
     */
    protected ?Collection $promotedTagCollection;

    /**
     * Create a new BlogPost
     *
     * @param Post $model
     */
    public function __construct(Post $model)
    {
        $this->model = $model;

        $ctrl = $this->getController();
        if ($ctrl && ($layout = $ctrl->getLayout()) !== null) {
            if (($posts = $layout->getComponent('blogPosts')) !== null) {
                $props = $posts->getProperties();
                $model->setUrl($props['postPage'], $ctrl);
                $model->categories->each(fn ($cat) => $cat->setUrl($props['categoryPage'], $ctrl));
            }

            $props = [
                'archiveAuthor' => 'blog/author',
                'archiveDate' => 'blog/date',
                'archiveTag' => 'blog/tag',
            ];
            $model->spanjaan_blogportal_tags->each(fn ($tag) => $tag->setUrl($props['archiveTag'], $ctrl));
        }
    }


    /**
     * Call Dynamic Property Method
     *
     * @param string $method
     * @param ?array $arguments
     * @return void
     */
    public function __call($method, $arguments = [])
    {
        $methodName = str_replace('_', '', 'get' . ucwords($method, '_'));

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}(...$arguments);
        } else {
            return null;
        }
    }

    /**
     * Get current CMS Controller
     *
     * @return Controller|null
     */
    protected function getController(): ?Controller
    {
        return Controller::getController();
    }

    /**
     * Get Estimated ReadTime
     *
     * @return array|string
     */
    public function getDetailReadTime($string = true)
    {
        $content = strip_tags($this->model->content_html);
        $count = str_word_count($content);

        $amount = $count / 200;
        $minutes = intval($amount);
        $seconds = intval(($minutes > 0 ? $amount - $minutes : $amount) * 0.60 * 100);

        if (!$string) {
            return [
                'minutes' => $minutes,
                'seconds' => $seconds,
            ];
        } else {
            if ($minutes === 0) {
                return trans('spanjaan.blogportal::lang.model.post.read_time_sec', [
                    'sec' => $seconds
                ]);
            } else {
                return trans('spanjaan.blogportal::lang.model.post.read_time', [
                    'min' => $minutes,
                    'sec' => $seconds
                ]);
            }
        }
    }

    /**
     * Get Published Ago Date/Time
     *
     * @return string
     */
    public function getDetailPublishedAgo($long = false, $until = null): string
    {
        return $this->model->published_at->diffForHumans();
    }

    /**
     * Get Post Comments
     *
     * @return mixed
     */
    public function getComments()
    {
        return $this->model->spanjaan_blogportal_comments;
    }

    /**
     * Get Post Comments Count
     *
     * @return integer
     */
    public function getCommentsCount()
    {
        return $this->model->spanjaan_blogportal_comments->count();
    }

    /**
     * Get Post Tags
     *
     * @return mixed
     */
    public function getTags()
    {
        if (empty($this->tagCollection)) {
            $this->tagCollection = $this->model->spanjaan_blogportal_tags;

            if (($ctrl = $this->getController()) !== null) {
                $viewBag = $ctrl->getLayout()->getViewBag()->getProperties();
                if (isset($viewBag['blogportalTagPage'])) {
                    $this->tagCollection->each(
                        fn ($tag) => $tag->setUrl($viewBag['blogportalTagPage'], $ctrl)
                    );
                }
            }
        }
        return $this->tagCollection;
    }

    /**
     * Get Promoted Post Tags
     *
     * @return mixed
     */
    public function getPromotedTags()
    {
        if (empty($this->promotedTagCollection)) {
            $this->promotedTagCollection = $this->model->spanjaan_blogportal_tags->where('promote', '1');

            if (($ctrl = $this->getController()) !== null) {
                $viewBag = $ctrl->getLayout()->getViewBag()->getProperties();
                if (isset($viewBag['blogportalTagPage'])) {
                    $this->promotedTagCollection->each(
                        fn ($tag) => $tag->setUrl($viewBag['blogportalTagPage'], $ctrl)
                    );
                }
            }
        }
        return $this->promotedTagCollection;
    }

    /**
     * Get View Counter
     *
     * @return integer
     */
    public function getViews()
    {
        if (!empty($this->model->spanjaan_blogportal_views)) {
            return intval($this->model->spanjaan_blogportal_views);
        } else {
            return 0;
        }
    }

    /**
     * Get Unique View Counter
     *
     * @return integer
     */
    public function getUniqueViews()
    {
        if (!empty($this->model->spanjaan_blogportal_unique_views)) {
            return intval($this->model->spanjaan_blogportal_unique_views);
        } else {
            return 0;
        }
    }

    /**
     * Get Visitors
     *
     * @return void
     */
    public function getVisitors()
    {
    }

    /**
     * Check if current Visitor has seen the page already.
     *
     * @return boolean
     */
    public function getHasSeen()
    {
        $visitor = Visitor::currentUser();
        return $visitor->hasSeen($this->model);
    }

    /**
     * Get Author
     *
     * @return boolean
     */
    public function getAuthor()
    {
        return $this->model->user;
    }

    /**
     * Get Next Blog Posts
     *
     * @param integer $limit
     * @param boolean $sameCategories
     * @return mixed
     */
    public function getNext($limit = 1, $sameCategories = false)
    {
        $query = $this->model->applySibling()
            ->with('categories');

        if ($sameCategories) {
            $categories = $this->model->categories->map(fn ($item) => $item->id)->all();

            $query->whereHas('categories', function ($query) use ($categories) {
                return $query->whereIn('winter_blog_categories.id', $categories);
            });
        }

        if ($limit > 1) {
            $query->limit($limit);
        }

        return $limit > 1 ? $query->get() : $query->first();
    }

    /**
     * Get Previous Blog Posts
     *
     * @param integer $limit
     * @param boolean $sameCategories
     * @return mixed
     */
    public function getPrevious($limit = 1, $sameCategories = false)
    {
        $query = $this->model->applySibling(-1)
            ->with('categories');

        if ($sameCategories) {
            $categories = $this->model->categories->map(fn ($item) => $item->id)->all();

            $query->whereHas('categories', function ($query) use ($categories) {
                return $query->whereIn('winter_blog_categories.id', $categories);
            });
        }

        if ($limit > 1) {
            $query->limit($limit);
        }

        return $limit > 1 ? $query->get() : $query->first();
    }

    /**
     * Get Previous Blog Posts (Alias)
     *
     * @param integer $limit
     * @param boolean $sameCategories
     * @return mixed
     */
    public function getPrev($limit = 1, $sameCategories = false)
    {
        return $this->getPrevious($limit, $sameCategories);
    }

    /**
     * Get Similar Blog Posts
     *
     * @param integer $limit
     * @param array $exclude
     * @return mixed
     */
    public function getRelated($limit = 5, $exclude = [])
    {
        $tags = $this->model->spanjaan_blogportal_tags->map(fn ($item) => $item->id)->all();
        $categories = $this->model->categories->map(fn ($item) => $item->id)->all();

        // Exclude
        $excludes = [];
        if (!empty($exclude)) {
            $excludes = is_array($exclude) ? $exclude : [$exclude];
            $excludes = array_map('intval', $excludes);
        }
        $excludes[] = $this->model->id;

        // Query
        $query = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->whereHas('categories', function ($query) use ($categories) {
                return $query->whereIn('winter_blog_categories.id', $categories);
            })
            ->whereHas('spanjaan_blogportal_tags', function ($query) use ($tags) {
                return $query->whereIn('spanjaan_blogportal_tags.id', $tags);
            })
            ->limit($limit);

        // Return Result
        $result = $query->get()->filter(fn ($item) => !in_array($item['id'], $excludes))->all();
        return $result;
    }

    /**
     * Get Random Blog Posts
     *
     * @param integer $limit
     * @param array $exclude
     * @return mixed
     */
    public function getRandom($limit = 5, $exclude = [])
    {
        // Exclude
        $excludes = [];
        if (!empty($exclude)) {
            $excludes = is_array($exclude) ? $exclude : [$exclude];
            $excludes = array_map('intval', $excludes);
        }
        $excludes[] = $this->model->id;

        // Query
        $query = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])->limit($limit);

        // Return Result
        $result = $query->get()->filter(fn ($item) => !in_array($item['id'], $excludes))->all();
        return $result;
    }
}
