<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use SpAnjaan\BlogPortal\Models\Comment;

class Comments extends Controller
{
    /** @var array<string> */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /** @var string */
    public $formConfig = 'config_form.yaml';

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
        BackendMenu::setContext('Winter.Blog', 'blog', 'spanjaan_blogportal_comments');
        $this->addCss('/plugins/spanjaan/blogportal/assets/css/comments-styles.css');
    }

    /**
     * Preview Comment
     *
     * @param mixed $id
     * @return mixed
     */
    public function preview($id)
    {
        $record = $this->formFindModelObject($id);

        $this->makeLists();

        $listQuery = $this->listGetWidget()->prepareQuery();
        $totalRecords = $listQuery->count();

        $previousId = 0;
        $nextId = 0;
        $currentIndex = null;
        $recordIndex = 0;

        foreach ($listQuery->get() as $listRecord) {
            $recordIndex++;
            if ($listRecord->getKey() === $record->getKey()) {
                $currentIndex = $recordIndex;
            } elseif ($currentIndex !== null) {
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

    /**
     * Inject Row Class for List
     *
     * @param mixed $record
     * @param mixed $definition
     * @return string
     */
    public function listInjectRowClass($record, $definition): string
    {
        return match ($record->status) {
            'pending' => 'comment-pending',
            'spam'    => 'comment-spam',
            'rejected'=> 'comment-rejected',
            'approved'=> 'comment-approved',
            default   => '',
        };
    }

    /**
     * Get Comment Statistics
     *
     * @param string $part
     * @return int|null
     */
    public static function getCommentStats(string $part): ?int
    {
        $stats = [
            'all_count'      => Comment::query()->count(),
            'approved_count' => Comment::where('status', 'approved')->count(),
            'rejected_count' => Comment::where('status', 'rejected')->count(),
            'spam_count'     => Comment::where('status', 'spam')->count(),
            'pending_count'  => Comment::where('status', 'pending')->count(),
        ];

        return $stats[$part] ?? null;
    }
}
