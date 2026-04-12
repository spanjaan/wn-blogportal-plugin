<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use DateInterval;
use DateTime;
use Lang;
use Winter\Blog\Models\Post;

class PostsStatistics extends ReportWidgetBase
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
            'defaultDateRange' => [
                'title'       => 'spanjaan.blogportal::lang.components.post.date_range',
                'description' => 'spanjaan.blogportal::lang.components.post.date_range_comment',
                'type'        => 'dropdown',
                'default'     => '14 days',
            ],
        ];
    }

    /**
     * Get Default Date Range Options
     *
     * @return array
     */
    public function getDefaultDateRangeOptions(): array
    {
        return [
            '7 days'   => Lang::get('spanjaan.blogportal::lang.components.post.7days'),
            '14 days'  => Lang::get('spanjaan.blogportal::lang.components.post.14days'),
            '31 days'  => Lang::get('spanjaan.blogportal::lang.components.post.31days'),
            '3 months' => Lang::get('spanjaan.blogportal::lang.components.post.3months'),
            '6 months' => Lang::get('spanjaan.blogportal::lang.components.post.6months'),
            '12 months' => Lang::get('spanjaan.blogportal::lang.components.post.12months'),
        ];
    }

    /**
     * Load Widget Assets
     *
     * @return void
     */
    protected function loadAssets(): void
    {
        $this->addCss('css/post-stats.css');
    }

    /**
     * Get Published Statistics
     *
     * @param string $range
     * @return array
     */
    protected function getPublishedStatistics(string $range): array
    {
        $interval = DateInterval::createFromDateString($range);
        $datetime = (new DateTime())
            ->setTime(0, 0, 0, 0)
            ->sub($interval);

        $posts = Post::where('published', '1')
            ->where('published_at', '>=', $datetime->format('Y-m-d') . ' 00:00:00')
            ->get();

        $number = (int) explode(' ', $range)[0];
        $steps = (strpos($range, 'days') === false ? $number * 31 : $number) / 7;
        $result = [];

        for ($i = 0; $i < 7; $i++) {
            $step = (int) ($i === 6 ? ceil($steps) : floor($steps));

            $timestamp = $datetime->getTimestamp() + ($i * $step * 24 * 60 * 60);
            $start = date('Y-m-d', $timestamp) . ' 00:00:00';
            $end = date('Y-m-d', $timestamp + ($step * 24 * 60 * 60)) . ' 00:00:00';

            $count = $posts->whereBetween('published_at', [$start, $end])->count();
            if ($steps === 1) {
                $result[date('d. M.', $timestamp)] = $count;
            } else {
                $key = date('d. M.', $timestamp) . ' - ' . date('d. M.', $timestamp + ($step * 24 * 60 * 60));
                $result[$key] = $count;
            }
        }

        return $result;
    }

    /**
     * Get General Statistics
     *
     * @param string $range
     * @return array
     */
    protected function getGeneralStatistics(string $range): array
    {
        $interval = DateInterval::createFromDateString($range);
        $datetime = (new DateTime())
            ->setTime(0, 0, 0, 0)
            ->sub($interval);

        $posts = Post::where('published', '1')
            ->where('published_at', '>=', $datetime->format('Y-m-d') . ' 00:00:00')
            ->get();

        $number = (int) explode(' ', $range)[0];
        $steps = (strpos($range, 'days') === false ? $number * 31 : $number) / 7;
        $result = [
            'views'    => [],
            'visitors' => [],
        ];

        for ($i = 0; $i < 7; $i++) {
            $step = (int) ($i === 6 ? ceil($steps) : floor($steps));

            $timestamp = $datetime->getTimestamp() + ($i * $step * 24 * 60 * 60);
            $start = date('Y-m-d', $timestamp) . ' 00:00:00';
            $end = date('Y-m-d', $timestamp + ($step * 24 * 60 * 60)) . ' 00:00:00';

            $count = $posts->whereBetween('published_at', [$start, $end]);
            $result['views'][] = '[' . ($timestamp * 1000) . ', ' . $count->sum('spanjaan_blogportal_views') . ']';
            $result['visitors'][] = '[' . ($timestamp * 1000) . ', ' . $count->sum('spanjaan_blogportal_unique_views') . ']';
        }

        return $result;
    }

    /**
     * Render Widget
     *
     * @return string
     */
    public function render(): string
    {
        $range = $this->property('defaultDateRange', '14 days');
        if (!array_key_exists($range, $this->getDefaultDateRangeOptions())) {
            $range = '14 days';
        }

        return $this->makePartial('widget', [
            'range'               => $range,
            'publishedStatistics' => $this->makePartial('published-statistics', [
                'statistics' => $this->getPublishedStatistics($range),
            ]),
            'generalStatistics'  => $this->makePartial('general-statistics', [
                'statistics' => $this->getGeneralStatistics($range),
            ]),
        ]);
    }

    /**
     * Change Range AJAX Handler
     *
     * @return array
     */
    public function onChangeRange(): array
    {
        $range = input('range');
        if (!array_key_exists($range, $this->getDefaultDateRangeOptions())) {
            $range = '14 days';
        }

        return [
            'range'                  => $range,
            '#publishedStatistics'   => $this->makePartial('published-statistics', [
                'statistics' => $this->getPublishedStatistics($range),
            ]),
            '#generalStatistics'    => $this->makePartial('general-statistics', [
                'statistics' => $this->getGeneralStatistics($range),
            ]),
        ];
    }
}
