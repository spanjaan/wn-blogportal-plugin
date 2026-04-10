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
     * Resolved page names cache
     *
     * @var array
     */
    protected array $pageNames = [];

    /**
     * Default page name fallbacks
     *
     * @var array
     */
    protected static array $pageDefaults = [
        'postPage'    => 'blog/post',
        'categoryPage'=> 'blog/category',
        'tagPage'     => 'blog/tag',
        'authorPage'  => 'blog/author',
        'datePage'    => 'blog/date',
    ];

    /**
     * Create a new BlogPost
     *
     * @param Post $model
     */
    public function __construct(Post $model)
    {
        $this->model = $model;
        $this->resolvePageNames();

        $ctrl = $this->getController();
        if ($ctrl === null) {
            return;
        }

        $model->setUrl($this->pageNames['postPage'], $ctrl);
        $model->categories->each(fn($cat) => $cat->setUrl($this->pageNames['categoryPage'], $ctrl, ['page' => null]));
        $model->spanjaan_blogportal_tags->each(fn($tag) => $tag->setUrl($this->pageNames['tagPage'], $ctrl, ['page' => null]));
    }

    /**
     * Resolve all page names from components, viewBag, or defaults.
     * Priority: component properties > viewBag > hardcoded defaults
     *
     * @return void
     */
    protected function resolvePageNames(): void
    {
        // Start with defaults
        $this->pageNames = static::$pageDefaults;

        $ctrl = $this->getController();
        if ($ctrl === null) {
            return;
        }

        $layout = $ctrl->getLayout();
        if ($layout === null) {
            return;
        }

        // ViewBag — second priority (overrides defaults)
        $viewBag = $layout->getViewBag()->getProperties();
        $viewBagMap = [
            'postPage'    => 'postPage',
            'categoryPage'=> 'categoryPage',
            'tagPage'     => 'blogportalTagPage',
            'authorPage'  => 'blogportalAuthorPage',
            'datePage'    => 'blogportalDatePage',
        ];
        foreach ($viewBagMap as $key => $vbKey) {
            if (!empty($viewBag[$vbKey])) {
                $this->pageNames[$key] = $viewBag[$vbKey];
            }
        }

        // Component properties — highest priority (overrides viewBag)
        $componentMap = [
            'postPage'    => ['blogPosts',                 'postPage'],
            'categoryPage'=> ['blogPosts',                 'categoryPage'],
            'tagPage'     => ['blogportalPostsByTag',      'postPage'],
            'authorPage'  => ['blogportalPostsByAuthor',   'postPage'],
            'datePage'    => ['blogportalPostsByDate',     'postPage'],
        ];
        foreach ($componentMap as $key => [$componentName, $propName]) {
            if (($comp = $layout->getComponent($componentName)) !== null) {
                $props = $comp->getProperties();
                if (!empty($props[$propName])) {
                    $this->pageNames[$key] = $props[$propName];
                }
            }
        }
    }

    /**
     * Call Dynamic Property Method
     *
     * @param string $method
     * @param array $arguments
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
        
        if ($count === 0) {
            if (!$string) {
                return ['minutes' => 0, 'seconds' => 0];
            }
            return trans('spanjaan.blogportal::lang.model.post.read_time_sec', ['sec' => 0]);
        }
        
        $minutes = intdiv($count, 200);
        $remaining = $count % 200;
        $seconds = intval(($remaining / 200) * 60);

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
     * @return string
     */
    public function getDetailPublishedAgo(): string
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

            $ctrl = $this->getController();
            if ($ctrl !== null) {
                $this->tagCollection->each(
                    fn($tag) => $tag->setUrl($this->pageNames['tagPage'], $ctrl, ['page' => null])
                );
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

            $ctrl = $this->getController();
            if ($ctrl !== null) {
                $this->promotedTagCollection->each(
                    fn($tag) => $tag->setUrl($this->pageNames['tagPage'], $ctrl, ['page' => null])
                );
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
        $shareCount = Sharecount::where('post_id', $this->model->id)->first();

        return $shareCount
            ? $shareCount->facebook + $shareCount->twitter + $shareCount->linkedin + $shareCount->whatsapp
            : 0;
    }

    /**
     * Get View Counter
     *
     * @return int
     */
    public function getViews(): int
    {
        return !empty($this->model->spanjaan_blogportal_views)
            ? intval($this->model->spanjaan_blogportal_views)
            : 0;
    }

    /**
     * Get Unique View Counter
     *
     * @return int
     */
    public function getUniqueViews(): int
    {
        return !empty($this->model->spanjaan_blogportal_unique_views)
            ? intval($this->model->spanjaan_blogportal_unique_views)
            : 0;
    }

    /**
     * Check if current Visitor has seen the page already.
     *
     * @return bool
     */
    public function getHasSeen(): bool
    {
        $visitor = Visitor::currentUser();
        return $visitor->hasSeen($this->model);
    }

    /**
     * Get Author
     *
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->model->user;
    }

    /**
     * Get Author Archive URL
     *
     * @return string
     */
    public function getAuthorUrl(): string
    {
        $ctrl = $this->getController();
        if ($ctrl === null || $this->model->user === null) {
            return '';
        }

        $user = $this->model->user;
        $slug = !empty($user->spanjaan_blogportal_author_slug)
            ? $user->spanjaan_blogportal_author_slug
            : $user->login;

        return $ctrl->pageUrl($this->pageNames['authorPage'], ['slug' => $slug, 'page' => null]);
    }

    /**
     * Get Date Archive URL
     *
     * @return string
     */
    public function getDateUrl(): string
    {
        $ctrl = $this->getController();
        if ($ctrl === null) {
            return '';
        }

        return $ctrl->pageUrl($this->pageNames['datePage'], [
            'date' => $this->model->published_at->format('Y-m'),
            'page' => null,
        ]);
    }

    /**
     * Get the next blog post.
     *
     * @param int $limit
     * @param bool $sameCategories
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
     * @param int $limit
     * @param bool $sameCategories
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
    protected function setPostUrl($posts): void
    {
        $ctrl = $this->getController();
        if ($ctrl === null) {
            return;
        }

        if ($posts instanceof Collection) {
            $posts->each(fn($post) => $post->setUrl($this->pageNames['postPage'], $ctrl));
        } else {
            $posts->setUrl($this->pageNames['postPage'], $ctrl);
        }
    }

    /**
     * Get Similar Blog Posts
     *
     * @param int $limit
     * @param array $exclude
     * @return Collection
     */
    public function getRelated(int $limit = 5, array $exclude = []): Collection
    {
        $tags       = $this->model->spanjaan_blogportal_tags->pluck('id')->all();
        $categories = $this->model->categories->pluck('id')->all();
        $excludes   = array_merge([$this->model->id], $exclude);

        return Post::isPublished()
            ->with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->where(function ($query) use ($categories, $tags) {
                $query->whereHas('categories', function ($query) use ($categories) {
                    $query->whereIn('winter_blog_categories.id', $categories);
                })->orWhereHas('spanjaan_blogportal_tags', function ($query) use ($tags) {
                    $query->whereIn('spanjaan_blogportal_tags.id', $tags);
                });
            })
            ->whereNotIn('id', $excludes)
            ->limit($limit)
            ->get();
    }

    /**
     * Get Random Blog Posts
     *
     * @param int $limit
     * @param array $exclude
     * @return Collection
     */
    public function getRandom(int $limit = 5, array $exclude = []): Collection
    {
        $excludes = array_merge([$this->model->id], array_map('intval', $exclude));

        return Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->whereNotIn('id', $excludes)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}