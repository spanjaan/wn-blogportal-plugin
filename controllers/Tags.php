<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class Tags extends Controller
{
    /**
     * Implemented Interfaces
     *
     * @var array
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    /**
     * Form Configuration File
     *
     * @var string
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List Configuration File
     *
     * @var string
     */
    public $listConfig = 'config_list.yaml';

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Winter.Blog', 'blog', 'spanjaan_blogportal_tags');
    }
}
