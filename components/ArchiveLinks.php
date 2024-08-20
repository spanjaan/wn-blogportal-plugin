<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Components;

use Cms\Classes\ComponentBase;
use Winter\Blog\Models\Post;
use Cms\Classes\Page;
use Illuminate\Support\Facades\DB;

class ArchiveLinks extends ComponentBase
{
    /**
     * Component Details
     *
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Archive Links',
            'description' => 'Generates links to archive posts by date.'
        ];
    }

    /**
     * Define Component Properties
     *
     * @return array
     */
    public function defineProperties()
    {
        return [
            'archivePage' => [
                'title'       => 'Archive Page',
                'description' => 'Page name to use for the archive links.',
                'type'        => 'dropdown',
                'default'     => 'blog/date',
            ]
        ];
    }

    /**
     * Get the Archive Page Options
     *
     * @return array
     */
    public function getArchivePageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Generate the Archive Links
     *
     * @return void
     */
    public function onRun()
    {
        $this->page['archiveLinks'] = $this->generateArchiveLinks();
    }

    /**
     * Generate the Archive Links for each Year-Month
     *
     * @return array
     */
    protected function generateArchiveLinks()
    {
        $archiveLinks = [];
        $posts = Post::select(DB::raw('YEAR(published_at) as year, MONTH(published_at) as month, COUNT(*) as post_count'))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        foreach ($posts as $post) {
            $monthName = $this->getMonthName($post->month);
            $archiveLinks[] = [
                'year'      => $post->year,
                'month'     => $post->month,
                'monthName' => $monthName,
                'count'     => $post->post_count,
                'url'       => $this->controller->pageUrl($this->property('archivePage'), ['date' => $post->year . '-' . ltrim((string)$post->month, '0')])
            ];
        }

        return $archiveLinks;
    }

    /**
     * Get Month Name from Month Number
     *
     * @param int $monthNumber
     * @return string
     */
    protected function getMonthName(int $monthNumber): string
    {
        $dateObj = \DateTime::createFromFormat('!m', (string)$monthNumber);
        return $dateObj->format('F');
    }
}
