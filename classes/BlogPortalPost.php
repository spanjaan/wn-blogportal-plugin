<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Classes;

use Cms\Classes\Controller;
use Illuminate\Support\Collection;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Models\Visitor;
use SpAnjaan\BlogPortal\Models\Sharecount;

class BlogPortalPost
{
    /**
     * Post Model
     *
     * @var Post
     */
    protected Post $model;

    protected ?BlogPortalPost $blogportalSet;

    /**
     * Post Tag Collection
     *
     * @var ?Collection
     */
    protected ?Collection $tagCollection = null;

    /**
     * Post Promoted Tag Collection
     *
     * @var ?Collection
     */
    protected ?Collection $promotedTagCollection = null;

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
                $model->categories->each(fn($cat) => $cat->setUrl($props['categoryPage'], $ctrl));
            }

            // Check only new settings
            $viewBag = $layout->getViewBag()->getProperties();
            $props = [
                'archiveAuthor' => $viewBag['blogportalAuthorPage'] ?? 'blog/author',
                'archiveDate' => $viewBag['blogportalDatePage'] ?? 'blog/date',
                'archiveTag' => $viewBag['blogportalTagPage'] ?? 'blog/tag',
            ];
            
            $model->spanjaan_blogportal_tags->each(fn($tag) => $tag->setUrl($props['archiveTag'], $ctrl));
        }
    }

    /**
     * Call Dynamic Property Method
     *
     * @param string $method
     * @param ?array $arguments
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        $methodName = 'get' . str_replace('_', '', ucwords($method, '_'));

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}(...$arguments);
        }

        return null;
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
     * @param bool $string
     * @return array|string
     */
    public function getDetailReadTime(bool $string = true)
    {
        $content = strip_tags($this->model->content_html);
        $count = str_word_count($content);
        $minutes = intval($count / 200);
        $seconds = intval(($count % 200) / 200 * 60);

        if (!$string) {
            return ['minutes' => $minutes, 'seconds' => $seconds];
        }

        return $minutes === 0
            ? trans('spanjaan.blogportal::lang.model.post.read_time_sec', ['sec' => $seconds])
            : trans('spanjaan.blogportal::lang.model.post.read_time', ['min' => $minutes, 'sec' => $seconds]);
    }

    /**
     * Get Published Ago Date/Time
     *
     * @param bool $long
     * @param mixed $until
     * @return string
     */
    public function getDetailPublishedAgo(bool $long = false, $until = null): string
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
     * @return int
     */
    public function getCommentsCount(): int
    {
        return $this->model->spanjaan_blogportal_comments->count();
    }

    /**
     * Get Post Tags
     *
     * @return Collection
     */
    public function getTags(): Collection
    {
        if ($this->tagCollection === null) {
            $this->tagCollection = $this->model->spanjaan_blogportal_tags;

            if (($ctrl = $this->getController()) !== null) {
                $viewBag = $ctrl->getLayout()->getViewBag()->getProperties();
                if (isset($viewBag['blogportalTagPage'])) {
                    $this->tagCollection->each(fn($tag) => $tag->setUrl($viewBag['blogportalTagPage'], $ctrl));
                }
            }
        }

        return $this->tagCollection;
    }

    /**
     * Get Promoted Post Tags
     *
     * @return Collection
     */
    public function getPromotedTags(): Collection
    {
        if ($this->promotedTagCollection === null) {
            $this->promotedTagCollection = $this->model->spanjaan_blogportal_tags->where('promote', '1');

            if (($ctrl = $this->getController()) !== null) {
                $viewBag = $ctrl->getLayout()->getViewBag()->getProperties();
                if (isset($viewBag['blogportalTagPage'])) {
                    $this->promotedTagCollection->each(fn($tag) => $tag->setUrl($viewBag['blogportalTagPage'], $ctrl));
                }
            }
        }

        return $this->promotedTagCollection;
    }
    
    /**
     * Get Post Share Count
     *
     * @return int
     */
    public function getSharesCount(): int
    {
        // Fetch the share count record for this post
        $shareCount = \SpAnjaan\BlogPortal\Models\Sharecount::where('post_id', $this->model->id)->first();

        // Calculate total shares
        return $shareCount ? $shareCount->facebook + $shareCount->twitter + $shareCount->linkedin + $shareCount->whatsapp : 0;
    }

    
    /**
     * Get View Counter
     *
     * @return integer
     */
    public function getViews()
    {
        return !empty($this->model->spanjaan_blogportal_views)
            ? intval($this->model->spanjaan_blogportal_views)
            : 0;
    }

    /**
     * Get Unique View Counter
     *
     * @return integer
     */
    public function getUniqueViews()
    {
        return !empty($this->model->spanjaan_blogportal_unique_views)
            ? intval($this->model->spanjaan_blogportal_unique_views)
            : 0;
    }

    /**
     * Get Visitors
     *
     * @return void
     */
    public function getVisitors()
    {
        // Implement if needed
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
     * Get the next blog post.
     *
     * @param integer $limit
     * @param boolean $sameCategories
     * @return mixed
     */
    public function getNext(int $limit = 1, bool $sameCategories = false)
    {
        $query = $this->model->applySibling(1)->with('categories');

        if ($sameCategories) {
            $categories = $this->model->categories->pluck('id')->all();
            $query->whereHas('categories', function ($query) use ($categories) {
                $query->whereIn('winter_blog_categories.id', $categories);
            });
        }

        $nextPost = $limit > 1 ? $query->limit($limit)->get() : $query->first();

        if ($nextPost) {
            $this->setPostUrl($nextPost);
        }

        return $nextPost;
    }

    /**
     * Get the previous blog post.
     *
     * @param integer $limit
     * @param boolean $sameCategories
     * @return mixed
     */
    public function getPrevious(int $limit = 1, bool $sameCategories = false)
    {
        $query = $this->model->applySibling(-1)->with('categories');

        if ($sameCategories) {
            $categories = $this->model->categories->pluck('id')->all();
            $query->whereHas('categories', function ($query) use ($categories) {
                $query->whereIn('winter_blog_categories.id', $categories);
            });
        }

        $prevPost = $limit > 1 ? $query->limit($limit)->get() : $query->first();

        if ($prevPost) {
            $this->setPostUrl($prevPost);
        }

        return $prevPost;
    }

    /**
     * Set the URL for a post or a collection of posts.
     *
     * @param mixed $posts
     * @return void
     */
    protected function setPostUrl($posts)
    {
        $ctrl = $this->getController();
        if ($ctrl && ($layout = $ctrl->getLayout()) !== null) {
            // Attempt to get the blogPosts component
            $postsComponent = $layout->getComponent('blogPosts');

            // If the component exists, use its properties; otherwise, provide a fallback
            $props = $postsComponent ? $postsComponent->getProperties() : ['postPage' => 'blog/post'];

            if ($posts instanceof \Illuminate\Support\Collection) {
                $posts->each(function ($post) use ($props, $ctrl) {
                    $post->setUrl($props['postPage'], $ctrl);
                });
            } else {
                $posts->setUrl($props['postPage'], $ctrl);
            }
        }
    }


   
    /**
     * Get Similar Blog Posts
     *
     * @param int $limit
     * @param array $exclude
     * @return mixed
     */
    public function getRelated(int $limit = 5, array $exclude = [])
    {
        $tags = $this->model->spanjaan_blogportal_tags->pluck('id')->all();
        $categories = $this->model->categories->pluck('id')->all();
    
        $excludes = array_merge([$this->model->id], $exclude);
    
        $query = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->where(function ($query) use ($categories, $tags) {
                $query->whereHas('categories', function ($query) use ($categories) {
                    $query->whereIn('winter_blog_categories.id', $categories);
                })
                ->orWhereHas('spanjaan_blogportal_tags', function ($query) use ($tags) {
                    $query->whereIn('spanjaan_blogportal_tags.id', $tags);
                });
            })
            ->whereNotIn('id', $excludes)
            ->limit($limit);
    
        $relatedPosts = $query->get();
    
        return $relatedPosts;
    }
    

    /**
     * Get Random Blog Posts
     *
     * @param int $limit
     * @param array $exclude
     * @return mixed
     */
    public function getRandom(int $limit = 5, array $exclude = [])
    {
        $excludes = array_merge([$this->model->id], array_map('intval', $exclude));

        $query = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])->limit($limit);

        return $query->get()->filter(fn($item) => !in_array($item['id'], $excludes))->all();
    }

    /**
     * Get BlogPortal Attribute
     *
     * @return BlogPortalPost
     */
    public function getBlogportalAttribute(): BlogPortalPost
    {
        if (empty($this->blogportalSet)) {
            $this->blogportalSet = new BlogPortalPost($this->model);
        }
        return $this->blogportalSet;
    }
}
