<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Redirect;
use Winter\Blog\Components\Posts;
use Winter\Blog\Models\Post;

class PostsByDate extends Posts
{
    /**
     * Post Date Archive array
     *
     * @var array
     */
    public $date = [];

    /**
     * Post Date Archive Type
     *
     * @var ?string
     */
    public $dateType = null;

    /**
     * Formatted Date Archive String
     *
     * @var string
     */
    public $dateFormat = '';

    /**
     * Declare Component Details
     *
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'          => 'spanjaan.blogportal::lang.components.date.label',
            'description'   => 'spanjaan.blogportal::lang.components.date.comment'
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
        $properties['dateFilter'] = [
            'title'         => 'spanjaan.blogportal::lang.components.date.filter',
            'description'   => 'spanjaan.blogportal::lang.components.date.filter_comment',
            'type'          => 'string',
            'default'       => '{{ :date }}',
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
        $this->prepareVars();

        [$date, $type] = $this->loadDate();
        
        // Render 404 for invalid date
        if ($date === null) {
            return $this->render404();
        }

        $this->date = $this->page['date'] = $date;
        $this->dateType = $this->page['dateType'] = $type;
        $this->dateFormat = $this->page['dateFormat'] = $this->formatDate($date);

        // Retrieve posts based on the date
        $this->posts = $this->page['posts'] = $this->listPosts();

        // Render 404 if no posts found for the given date
        if ($this->posts->count() === 0) {
            return $this->render404();
        }

        $this->handlePaginationRedirect();
    }


    /**
     * Retrieve posts filtered by the specified date
     */
    protected function listPosts()
    {
        $date = $this->date;
        $categoryId = $this->category->id ?? null;
        $categorySlug = $this->category->slug ?? null;

        [$start_date, $end_date] = $this->getDateRange($date);
        $isPublished = !parent::checkEditor();

        $posts = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->whereBetween('published_at', [$start_date, $end_date])
            ->where('published_at', '<=', now())
            ->listFrontEnd([
                'page'             => $this->property('pageNumber'),
                'sort'             => $this->property('sortOrder'),
                'perPage'          => $this->property('postsPerPage'),
                'search'           => trim(input('search') ?? ''),
                'category'         => $categoryId,
                'published'        => $isPublished,
                'exceptPost'       => $this->normalizeArray($this->property('exceptPost')),
                'exceptCategories' => $this->normalizeArray($this->property('exceptCategories')),
            ]);

        $posts->each(function ($post) use ($categorySlug) {
            $post->setUrl($this->postPage, $this->controller, ['category' => $categorySlug]);

            $post->categories->each(function ($category) {
                $category->setUrl($this->categoryPage, $this->controller);
            });
        });

        return $posts;
    }
    
    /**
     * Determine the date range based on the provided date
     */
    protected function getDateRange(array $date): array
    {
        if (isset($date['day'])) {
            $start_date = "{$date['year']}-{$date['month']}-{$date['day']} 00:00:00";
            $end_date = "{$date['year']}-{$date['month']}-{$date['day']} 23:59:59";
        } elseif (isset($date['month'])) {
            $last_day = date('t', strtotime("{$date['year']}-{$date['month']}-01"));
            $start_date = "{$date['year']}-{$date['month']}-01 00:00:00";
            $end_date = "{$date['year']}-{$date['month']}-{$last_day} 23:59:59";
        } else {
            $start_date = "{$date['year']}-01-01 00:00:00";
            $end_date = "{$date['year']}-12-31 23:59:59";
        }

        return [$start_date, $end_date];
    }

    /**
     * Validate and parse the date from the property
     */
    protected function loadDate(): array
    {
        $dateFilter = $this->property('dateFilter');

        if (!is_string($dateFilter) || trim($dateFilter) === '') {
            return [null, null];
        }

        $dateFilter = trim($dateFilter);
        $parts = explode('-', $dateFilter);

        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            [$year, $month] = $parts;
            if ($year >= 1 && $month >= 1 && $month <= 12) {
                return [['year' => (int)$year, 'month' => (int)$month], 'month'];
            }
        } elseif (count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2])) {
            [$year, $month, $day] = $parts;
            if (checkdate((int)$month, (int)$day, (int)$year)) {
                return [['year' => (int)$year, 'month' => (int)$month, 'day' => (int)$day], 'day'];
            }
        } elseif (strlen($dateFilter) === 4 && is_numeric($dateFilter)) {
            return [['year' => (int)$dateFilter], 'year'];
        }

        return [null, null];
    }
    
    /**
     * Format the date for display
     */
    protected function formatDate(array $date): string
    {
        if (isset($date['day'])) {
            return date('F d, Y', strtotime("{$date['year']}-{$date['month']}-{$date['day']}"));
        } elseif (isset($date['month'])) {
            return date('F Y', strtotime("{$date['year']}-{$date['month']}-01"));
        }

        return (string)$date['year'];
    }

    /**
     * Normalize array input for filtering
     */
    protected function normalizeArray($value): array
    {
        return is_array($value) ? $value : preg_split('/,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Render the 404 page
     */
    protected function render404()
    {
        $this->setStatusCode(404);
        return $this->controller->run('404');
    }

    /**
     * Redirect to the correct pagination page if out of range
     */
    protected function handlePaginationRedirect()
    {
        $pageNumberParam = $this->paramName('pageNumber');
        $currentPage = $this->property('pageNumber');

        if ($pageNumberParam && $currentPage > ($lastPage = $this->posts->lastPage()) && $currentPage > 1) {
            Redirect::to($this->currentPageUrl([$pageNumberParam => $lastPage]));
        }
    }
}
