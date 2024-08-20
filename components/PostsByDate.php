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

        $properties['date404OnInvalid'] = [
            'title'         => 'spanjaan.blogportal::lang.components.date.404_on_invalid',
            'description'   => 'spanjaan.blogportal::lang.components.date.404_on_invalid_comment',
            'type'          => 'checkbox',
            'default'       => true,
            'group'         => 'spanjaan.blogportal::lang.components.blogportal_group',
        ];

        $properties['date404OnEmpty'] = [
            'title'         => 'spanjaan.blogportal::lang.components.date.404_on_empty',
            'description'   => 'spanjaan.blogportal::lang.components.date.404_on_empty_comment',
            'type'          => 'checkbox',
            'default'       => true,
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
        if (empty($date)) {
            if ($this->property('date404OnInvalid', true)) {
                $this->setStatusCode(404);
                return $this->controller->run('404');
            }
        }

        // Set Page Properties
        $this->date = $this->page['date'] = $date;
        $this->dateType = $this->page['dateType'] = $type;
        if (!empty($date)) {
            $this->dateFormat = $this->page['dateFormat'] = $this->formatDate($this->date);
            $this->posts = $this->page['posts'] = $this->listPosts();
        } else {
            $this->dateFormat = $this->page['dateFormat'] = null;
            $this->posts = $this->page['posts'] = [];
        }

        // Return 404 on empty date archives
        if ($this->posts->count() === 0) {
            if ($this->property('date404OnEmpty', true)) {
                $this->setStatusCode(404);
                return $this->controller->run('404');
            }
        }

        // Set Latest Page Number
        if ($pageNumberParam = $this->paramName('pageNumber')) {
            $currentPage = $this->property('pageNumber');

            if ($currentPage > ($lastPage = $this->posts->lastPage()) && $currentPage > 1) {
                return Redirect::to($this->currentPageUrl([$pageNumberParam => $lastPage]));
            }
        }
    }


    protected function listPosts()
    {
        $date = $this->date;
    
        // Ensure $this->category is initialized or handle null case
        $categoryId = isset($this->category) ? $this->category->id : null;
        $categorySlug = isset($this->category) ? $this->category->slug : null;
    
        // Start and End Date
        if (isset($date['day'])) {
            $start_date = $date['year'] . '-' . str_pad((string)$date['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$date['day'], 2, '0', STR_PAD_LEFT) . ' 00:00:00';
            $end_date = $date['year'] . '-' . str_pad((string)$date['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$date['day'], 2, '0', STR_PAD_LEFT) . ' 23:59:59';
        } elseif (isset($date['month'])) {
            $last_day = date('t', strtotime("{$date['year']}-" . str_pad((string)$date['month'], 2, '0', STR_PAD_LEFT) . '-01'));
            $start_date = $date['year'] . '-' . str_pad((string)$date['month'], 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
            $end_date = $date['year'] . '-' . str_pad((string)$date['month'], 2, '0', STR_PAD_LEFT) . '-' . $last_day . ' 23:59:59';
        } else {
            $start_date = $date['year'] . '-01-01 00:00:00';
            $end_date = $date['year'] . '-12-31 23:59:59';
        }
    
        /*
         * List all the posts, eager load their categories
         */
        $isPublished = !parent::checkEditor();
    
        $posts = Post::with(['categories', 'featured_images', 'spanjaan_blogportal_tags'])
            ->whereBetween('published_at', [$start_date, $end_date])
            ->where('published_at', '<=', date('Y-m-d')) // Filter published posts up to today
            ->listFrontEnd([
                'page'             => $this->property('pageNumber'),
                'sort'             => $this->property('sortOrder'),
                'perPage'          => $this->property('postsPerPage'),
                'search'           => trim(input('search') ?? ''),
                'category'         => $categoryId,
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
     * Load & Validate Date
     *
     * @return array
     */
    protected function loadDate()
    {
        if (empty($dateFilter = $this->property('dateFilter'))) {
            return [null, null];
        }
    
        // Parse date filter
        $parts = explode('-', $dateFilter);
        
        // Check if it's a year and month format (YYYY-MM)
        if (count($parts) === 2 && strlen($parts[0]) === 4 && strlen($parts[1]) === 1) {
            $year = $parts[0];
            $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $day = null;
            $type = 'month';
        }
        // Check if it's a year and month and day format (YYYY-MM-DD)
        elseif (count($parts) === 3 && strlen($parts[0]) === 4 && strlen($parts[1]) === 2 && strlen($parts[2]) === 2) {
            $year = $parts[0];
            $month = $parts[1];
            $day = $parts[2];
            $type = 'day';
        } 
        // Check if it's just a year format (YYYY)
        elseif (strlen($dateFilter) === 4) {
            $year = $dateFilter;
            $month = null;
            $day = null;
            $type = 'year';
        } 
        else {
            return [null, null];
        }
    
        $date = [];
    
        // Validate Year
        if (is_numeric($year) && ($year = intval($year)) && $year >= 1970 && $year <= intval(date('Y'))) {
            $date['year'] = $year;
        } else {
            return [null, null];
        }
    
        // Validate Month
        if ($month !== null) {
            if (is_numeric($month) && ($month = intval($month)) && $month >= 1 && $month <= 12) {
                $date['month'] = $month;
            } else {
                return [null, null];
            }
        }
    
        // Validate Day
        if ($day !== null) {
            if (is_numeric($day) && ($day = intval($day)) && $day >= 1 && $day <= intval(date('t', strtotime("$year-$month-01")))) {
                $date['day'] = $day;
            } else {
                return [null, null];
            }
        }
    
        return [$date, $type];
    }
    


    /**
     * Format Date
     *
     * @param array $date
     * @return void
     */
    protected function formatDate(array $date)
    {
        if (isset($date['day'])) {
            return date('F, d. Y', strtotime("{$date['year']}-{$date['month']}-{$date['day']} 00:00:00"));
        } elseif (isset($date['month'])) {
            return date('F, Y', strtotime("{$date['year']}-{$date['month']}-01 00:00:00"));
        } elseif (isset($date['week'])) {
            $datetime = new \DateTime();
            $datetime->setISODate($date['year'], $date['week']);
            return $datetime->format('\WW, Y');
        } else {
            return $date['year'];
        }
    }
}
