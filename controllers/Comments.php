<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use SpAnjaan\BlogPortal\Models\Comment;

class Comments extends Controller
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
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Winter.Blog', 'blog', 'spanjaan_blogportal_comments');
        // Include CSS for the Comments backend list
        $this->addCss('/plugins/spanjaan/blogportal/assets/css/comments-styles.css');
    }

    //Initilize preview and display previous next button in preview page
    public function preview($id)
    {
        $record = $this->formFindModelObject($id);

        // Initialize the ListController
        $this->makeLists();

        // Get the position of the current record within the current list set
        $listQuery = $this->listGetWidget()->prepareQuery();
        $totalRecords = $listQuery->count();
        \DB::statement(\DB::raw('set @row_num=0'));
        $listQuery->selectRaw('@row_num:= @row_num + 1 as `record_position`');

        $previousId = 0;
        $nextId = 0;
        $currentIndex = null;
        // Note, if you have few records overall but massive sizes per record you can use
        // $listQuery->cursor() to make one DB query per record instead of loading them all at once
        foreach ($listQuery->get() as $listRecord) {
            if ($listRecord->getKey() === $record->getKey()) {
                $currentIndex = $listRecord->record_position;
                continue;
            } elseif ($currentIndex) {
                $nextId = $listRecord->getKey();
                break;
            } else {
                $previousId = $listRecord->getKey();
            }
        }

        $this->vars = array_merge($this->vars, [
            'previousUrl'  => $previousId ? Backend::url('spanjaan/blogportal/comments/preview/' . $previousId) : '',
            'nextUrl'      => $nextId ? Backend::url('spanjaan/blogportal/comments/preview/' . $nextId) : '',
            'currentIndex' => $currentIndex,
            'totalRecords' => $totalRecords,
            'updateUrl'    => Backend::url('spanjaan/blogportal/comments/update/' . $id),
        ]);

        return $this->asExtension('FormController')->preview($id);
    }

    // Add CSS classes based on comment status for different rows in the backend list
    public function listInjectRowClass($record, $definition)
    {
        $class = '';

        if ($record->status === 'pending') {
            $class = 'comment-pending';
        } elseif ($record->status === 'spam') {
            $class = 'comment-spam';
        } elseif ($record->status === 'rejected') {
            $class = 'comment-rejected';
        } elseif ($record->status === 'approved') {
            $class = 'comment-approved';
        }

        return $class;
    }

    // Display comments status counts in the backend toolbar
    public static function getCommentStats($part)
    {
        switch ($part) {
            case 'all_count':
                return Comment::count();
                break;

            case 'approved_count':
                return Comment::where('status', 'approved')->count();
                break;

            case 'rejected_count':
                return Comment::where('status', 'rejected')->count();
                break;

            case 'spam_count':
                return Comment::where('status', 'spam')->count();
                break;

            case 'pending_count':
                return Comment::where('status', 'pending')->count();
                break;

            default:
                return null;
                break;
        }
    }
}
