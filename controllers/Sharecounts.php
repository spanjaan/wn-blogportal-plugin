<?php namespace SpAnjaan\BlogPortal\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use SpAnjaan\BlogPortal\Models\Sharecount;

class Sharecounts extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Winter.Blog', 'blog', 'spanjaan_blogportal_sharecounts');
    }

    public static function getShareCount($part)
    {
        $sharecount = Sharecount::query();

        switch ($part) {
            case 'all_count':
                return $sharecount->sum('facebook') +
                       $sharecount->sum('twitter') +
                       $sharecount->sum('whatsapp') +
                       $sharecount->sum('linkedin');
                break;

            case 'facebook_count':
                return $sharecount->sum('facebook');
                break;

            case 'twitter_count':
                return $sharecount->sum('twitter');
                break;

            case 'whatsapp_count':
                return $sharecount->sum('whatsapp');
                break;

            case 'linkedin_count':
                return $sharecount->sum('linkedin');
                break;

            default:
                return 0;
                break;
        }
    }
}
