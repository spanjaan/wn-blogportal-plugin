<?php 

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use SpAnjaan\BlogPortal\Models\Sharecount;

class Sharecounts extends Controller
{
    // Implementing ListController behaviors
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    // Configuration files for List controllers
    public $listConfig = 'config_list.yaml';
    
    /**
     * Constructor to set up the controller's context in the backend menu.
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Winter.Blog', 'blog', 'spanjaan_blogportal_sharecounts');
    }

    /**
     * Get the total share count or individual platform share count.
     *
     * @param string $part The part of the share count to retrieve.
     * @return int The sum of share counts based on the provided part.
     */
    public static function getShareCount($part)
    {
        $sharecount = Sharecount::query();

        switch ($part) {
            case 'all_count':
                // Sum share counts across all platforms
                return $sharecount->sum('facebook') +
                       $sharecount->sum('twitter') +
                       $sharecount->sum('whatsapp') +
                       $sharecount->sum('linkedin');
                break;

            case 'facebook_count':
                // Return Facebook share count
                return $sharecount->sum('facebook');
                break;

            case 'twitter_count':
                // Return Twitter share count
                return $sharecount->sum('twitter');
                break;

            case 'whatsapp_count':
                // Return WhatsApp share count
                return $sharecount->sum('whatsapp');
                break;

            case 'linkedin_count':
                // Return LinkedIn share count
                return $sharecount->sum('linkedin');
                break;

            default:
                // Default to returning 0 if no valid part is provided
                return 0;
                break;
        }
    }
}
