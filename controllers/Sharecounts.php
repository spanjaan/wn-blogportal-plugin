<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use SpAnjaan\BlogPortal\Models\Sharecount;

class Sharecounts extends Controller
{
    /** @var array<string> */
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    /** @var string */
    public $listConfig = 'config_list.yaml';

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Winter.Blog', 'blog', 'spanjaan_blogportal_sharecounts');
    }

    /**
     * Get Share Count Statistics
     *
     * @param string $part
     * @return int
     */
    public static function getShareCount(string $part): int
    {
        $sharecount = Sharecount::query();

        return match ($part) {
            'all_count'      => (int) ($sharecount->sum('facebook')
                                + $sharecount->sum('twitter')
                                + $sharecount->sum('whatsapp')
                                + $sharecount->sum('linkedin')),
            'facebook_count' => (int) $sharecount->sum('facebook'),
            'twitter_count'  => (int) $sharecount->sum('twitter'),
            'whatsapp_count' => (int) $sharecount->sum('whatsapp'),
            'linkedin_count' => (int) $sharecount->sum('linkedin'),
            default          => 0,
        };
    }
}
