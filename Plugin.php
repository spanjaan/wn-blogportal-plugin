<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal;
use Event;
use Backend;
use Backend\Controllers\Users as BackendUsers;
use Backend\Facades\BackendAuth;
use Backend\Models\User as BackendUser;
use Backend\Widgets\Lists;
use Cms\Classes\Controller;
use Winter\Blog\Controllers\Posts;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Behaviors\BlogPortalBackendUserModel;
use SpAnjaan\BlogPortal\Behaviors\BlogPortalPostModel;
use SpAnjaan\BlogPortal\Models\Comment;
use SpAnjaan\BlogPortal\Models\Visitor;
use System\Classes\PluginBase;
use Winter\Translate\FormWidgets\MLRichEditor;
use SpAnjaan\BlogPortal\Models\BlogPortalSettings;

class Plugin extends PluginBase
{
    /**
     * Required Extensions
     *
     * @var array
     */
    public $require = [
        'Winter.Blog'
    ];
    
    // Define constants for event names
    private const MENU_EVENT = 'backend.menu.extendItems';
    private const FORM_EVENT = 'backend.form.extendFields';
    private const PAGE_EVENT = 'cms.page.end';

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'spanjaan.blogportal::lang.plugin.name',
            'description' => 'spanjaan.blogportal::lang.plugin.description',
            'author'      => 'S.p. Anjaan',
            'icon'        => 'icon-tags',
            'homepage'    => 'https://github.com/spanjaan/blogportal'
        ];
    }
    
    /**
     * Get BlogPortal Settings
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function config(string $key)
    {
        if (empty($this->blogportalSettings)) {
            $this->blogportalSettings = BlogPortalSettings::instance();
        }
        return $this->blogportalSettings->{$key} ?? BlogPortalSettings::defaultValue($key);
    }
    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        // Extend available sorting options
        Post::$allowedSortingOptions['spanjaan_blogportal_views asc']           = 'spanjaan.blogportal::lang.sorting.blogportal_views_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_views desc']          = 'spanjaan.blogportal::lang.sorting.blogportal_views_desc';
        Post::$allowedSortingOptions['spanjaan_blogportal_unique_views asc']    = 'spanjaan.blogportal::lang.sorting.blogportal_unique_views_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_unique_views desc']   = 'spanjaan.blogportal::lang.sorting.blogportal_unique_views_desc';
        Post::$allowedSortingOptions['spanjaan_blogportal_comments_count asc']  = 'spanjaan.blogportal::lang.sorting.blogportal_comments_count_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_comments_count desc'] = 'spanjaan.blogportal::lang.sorting.blogportal_comments_count_desc';
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        $this->extendBackendMenu();
        $this->extendRichEditor();
        $this->collectUniqueViews();
        $this->implementCustomModels();

        // Add other boot-time functionality if needed
    }

    private function extendBackendMenu()
    {
        // Add side menus to Winter.Blog
        Event::listen(self::MENU_EVENT, function ($manager) {
            $manager->addSideMenuItems('Winter.Blog', 'blog', [
                'spanjaan_blogportal_tags' => [
                    'label'         => 'spanjaan.blogportal::lang.model.tags.label',
                    'icon'          => 'icon-tags',
                    'code'          => 'spanjaan-blogportal-tags',
                    'owner'         => 'SpAnjaan.BlogPortal',
                    'url'           => Backend::url('spanjaan/blogportal/tags'),
                    'permissions'   => [
                        'spanjaan.blogportal.tags'
                    ]
                ],

                'spanjaan_blogportal_comments' => [
                    'label'         => 'spanjaan.blogportal::lang.model.comments.label',
                    'icon'          => 'icon-message',
                    'code'          => 'spanjaan-blogportal-comments',
                    'owner'         => 'SpAnjaan.BlogPortal',
                    'url'           => Backend::url('spanjaan/blogportal/comments'),
                    'counter'       => Comment::where('status', 'pending')->count(),
                    'permissions'   => [
                        'spanjaan.blogportal.comments'
                    ]
                ],

                'spanjaan_blogportal_sharecounts' => [
                    'label'         => 'spanjaan.blogportal::lang.model.sharecounts.label',
                    'icon'          => 'icon-share-nodes',
                    'code'          => 'spanjaan-blogportal-sharecounts',
                    'owner'         => 'SpAnjaan.BlogPortal',
                    'url'           => Backend::url('spanjaan/blogportal/sharecounts'),
                    'permissions'   => [
                        'spanjaan.blogportal.sharecounts'
                    ]
                ]
            ]);
        });
    }

    private function extendRichEditor()
    {
        // Check if the 'richeditor_setting' configuration is set to true
        if ($this->config('richeditor_setting') === '1') {
            // Extend Richeditor in blog content field.
            Event::listen(self::FORM_EVENT, function ($widget) {
                // Check if the controller is an instance of \Winter\Blog\Controllers\Posts
                // and the model is an instance of \Winter\Blog\Models\Post
                if (!($widget->getController() instanceof \Winter\Blog\Controllers\Posts
                    && $widget->model instanceof \Winter\Blog\Models\Post)) {
                    return;
                }
    
                // Continue with the rest of the code for extending fields
                $field = $widget->getField('content');
                if (class_exists('Winter\Translate\FormWidgets\MLRichEditor')) {
                    $field->config['widget'] = 'Winter\Translate\FormWidgets\MLRichEditor';
                } else {
                    $field->config['widget'] = 'Backend\FormWidgets\RichEditor';
                }
            });
        }
    }
    
    private function collectUniqueViews()
    {
        // Collect (Unique) Views
        Event::listen(self::PAGE_EVENT, function (Controller $ctrl) {
            $pageObject = $ctrl->getPageObject();
            if (property_exists($pageObject, 'vars')) {
                $post = $pageObject->vars['post'] ?? null;
            } elseif (property_exists($pageObject, 'controller')) {
                $post = $pageObject->controller->vars['post'] ?? null;
            } else {
                $post = null;
            }
            if (empty($post)) {
                return;
            }

            $guest = BackendAuth::getUser() === null;
            $visitor = Visitor::currentUser();
            if (!$visitor->hasSeen($post)) {
                if ($guest) {
                    $post->spanjaan_blogportal_unique_views = is_numeric($post->spanjaan_blogportal_unique_views) ? $post->spanjaan_blogportal_unique_views + 1 : 1;
                }
                $visitor->markAsSeen($post);
            }

            if ($guest) {
                $post->spanjaan_blogportal_views = is_numeric($post->spanjaan_blogportal_views) ? $post->spanjaan_blogportal_views + 1 : 1;

                if (!empty($post->url)) {
                    $url = $post->url;
                    unset($post->url);
                }

                $post->save();

                if (isset($url)) {
                    $post->url = $url;
                }
            }
        });
    }

    private function implementCustomModels()
    {
        // Implement custom Models for Post and BackendUser
        Post::extend(function ($model) {
            $model->implement[] = BlogPortalPostModel::class;
        });

        BackendUser::extend(function ($model) {
            $model->implement[] = BlogPortalBackendUserModel::class;
        });

        // Extend Form Fields on Posts Controller
        Posts::extendFormFields(function ($form, $model, $context) {
            // Check if the model is an instance of Post
            if (!$model instanceof Post) {
                return;
            }

            // Add Comments Field
            $form->addTabFields([
                'spanjaan_blogportal_comment_visible' => [
                    'tab'           => 'spanjaan.blogportal::lang.model.comments.label',
                    'type'          => 'switch',
                    'label'         => 'spanjaan.blogportal::lang.model.comments.post_visibility.label',
                    'comment'       => 'spanjaan.blogportal::lang.model.comments.post_visibility.comment',
                    'span'          => 'left',
                    'permissions'   => ['spanjaan.blogportal.comments.access_comments_settings']
                ],
                'spanjaan_blogportal_comment_mode' => [
                    'tab'           => 'spanjaan.blogportal::lang.model.comments.label',
                    'type'          => 'dropdown',
                    'label'         => 'spanjaan.blogportal::lang.model.comments.post_mode.label',
                    'comment'       => 'spanjaan.blogportal::lang.model.comments.post_mode.comment',
                    'showSearch'    => false,
                    'span'          => 'left',
                    'options'       => [
                        'open'          => 'spanjaan.blogportal::lang.model.comments.post_mode.open',
                        'restricted'    => 'spanjaan.blogportal::lang.model.comments.post_mode.restricted',
                        'private'       => 'spanjaan.blogportal::lang.model.comments.post_mode.private',
                        'closed'        => 'spanjaan.blogportal::lang.model.comments.post_mode.closed',
                    ],
                    'permissions'       => ['spanjaan.blogportal.comments.access_comments_settings']
                ],
            ]);

            // Add Tags Field
            $form->addTabFields([
                'spanjaan_blogportal_tags' => [
                    'label'         => 'spanjaan.blogportal::lang.model.tags.label',
                    'mode'          => 'relation',
                    'tab'           => 'winter.blog::lang.post.tab_categories',
                    'type'          => 'taglist',
                    'nameFrom'      => 'slug',
                    'permissions'   => ['spanjaan.blogportal.tags']
                ]
            ]);
        });

        // Extend List Columns on Posts Controller
        Posts::extendListColumns(function (Lists $list, $model) {
            // Check if the model is an instance of Post
            if (!$model instanceof Post) {
                return;
            }

            // Add custom list columns
            $list->addColumns([
                'spanjaan_blogportal_views' => [
                    'label'     => 'spanjaan.blogportal::lang.model.visitors.views',
                    'type'      => 'number',
                    'select'    => 'concat(winter_blog_posts.spanjaan_blogportal_views, " / ", winter_blog_posts.spanjaan_blogportal_unique_views)',
                    'align'     => 'left'
                ]
            ]);
        });

        // Add Posts Filter Scope
        Posts::extendListFilterScopes(function ($filter) {
            // Add custom filter scope for tags
            $filter->addScopes([
                'spanjaan_blogportal_tags' => [
                    'label'      => 'spanjaan.blogportal::lang.model.tags.label',
                    'modelClass' => 'SpAnjaan\BlogPortal\Models\Tag',
                    'nameFrom'   => 'slug',
                    'scope'      => 'FilterTags'
                ]
            ]);
        });

        // Extend Backend Users Controller
        BackendUsers::extendFormFields(function ($form, $model, $context) {
            // Check if the model is an instance of BackendUser
            if (!$model instanceof BackendUser) {
                return;
            }

            // Add custom form fields for BackendUser
            $form->addTabFields([
                'spanjaan_blogportal_display_name' => [
                    'label'         => 'spanjaan.blogportal::lang.model.users.displayName',
                    'description'   => 'spanjaan.blogportal::lang.model.users.displayNameDescription',
                    'tab'           => 'backend::lang.user.account',
                    'type'          => 'text',
                    'span'          => 'left'
                ],
                'spanjaan_blogportal_author_slug' => [
                    'label'         => 'spanjaan.blogportal::lang.model.users.authorSlug',
                    'description'   => 'spanjaan.blogportal::lang.model.users.authorSlugDescription',
                    'tab'           => 'backend::lang.user.account',
                    'type'          => 'text',
                    'span'          => 'right'
                ],
                'spanjaan_blogportal_about_me' => [
                    'label'         => 'spanjaan.blogportal::lang.model.users.aboutMe',
                    'description'   => 'spanjaan.blogportal::lang.model.users.aboutMeDescription',
                    'tab'           => 'backend::lang.user.account',
                    'type'          => 'textarea',
                ]
            ]);
        });
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            \SpAnjaan\BlogPortal\Components\PostsByAuthor::class       => 'blogportalPostsByAuthor',
            \SpAnjaan\BlogPortal\Components\PostsByCommentCount::class => 'blogportalPostsByCommentCount',
            \SpAnjaan\BlogPortal\Components\PostsByDate::class         => 'blogportalPostsByDate',
            \SpAnjaan\BlogPortal\Components\PostsByTag::class          => 'blogportalPostsByTag',
            \SpAnjaan\BlogPortal\Components\CommentList::class         => 'blogportalCommentList',
            \SpAnjaan\BlogPortal\Components\CommentSection::class      => 'blogportalCommentSection',
            \SpAnjaan\BlogPortal\Components\Tags::class                => 'blogportalTags',
            \SpAnjaan\BlogPortal\Components\PopularPosts::class        => 'blogportalPopularPosts',
            \SpAnjaan\BlogPortal\Components\ArchiveLinks::class        => 'blogportalArchiveLinks',
            \SpAnjaan\BlogPortal\Components\ShareButtons::class        => 'blogportalShareButtons',
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'spanjaan.blogportal.comments' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.access_comments',
                'comment'   => 'spanjaan.blogportal::lang.permissions.access_comments_comment',
            ],
            'spanjaan.blogportal.comments.access_comments_settings' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.manage_post_settings'
            ],
            'spanjaan.blogportal.comments.moderate_comments' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.moderate_comments'
            ],
            'spanjaan.blogportal.comments.delete_comments' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.delete_comments'
            ],
            'spanjaan.blogportal.tags' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.access_tags',
                'comment'   => 'spanjaan.blogportal::lang.permissions.access_tags_comment',
            ],
            'spanjaan.blogportal.tags.promoted' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.promote_tags'
            ],
            'spanjaan.blogportal.sharecounts' => [
                'tab'       => 'winter.blog::lang.blog.tab',
                'label'     => 'spanjaan.blogportal::lang.permissions.sharecounts'
            ]
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return [];
    }

    /**
     * Registers settings navigation items for this plugin.
     *
     * @return array
     */
    public function registerSettings()
    {
        return [
            'spanjaan_blogportal_config' => [
                'label'         => 'spanjaan.blogportal::lang.settings.config.label',
                'description'   => 'spanjaan.blogportal::lang.settings.config.description',
                'category'      => 'winter.blog::lang.blog.menu_label',
                'icon'          => 'icon-pencil-square-o',
                'class'         => 'SpAnjaan\BlogPortal\Models\BlogPortalSettings',
                'order'         => 500,
                'keywords'      => 'blog post tag comments',
                'permissions'   => ['winter.blog.manage_settings'],
                'size'          => 'adaptive'
            ]
        ];
    }

    /**
     * Registers any report widgets provided by this package.
     *
     * @return array
     */
    public function registerReportWidgets()
    {
        return [
            \SpAnjaan\BlogPortal\ReportWidgets\CommentsList::class => [
                'label'         => 'spanjaan.blogportal::lang.widgets.comments_list.label',
                'context'       => 'dashboard',
                'permission'    => [
                    'winter.blog.access_other_posts',
                    'spanjaan.blogportal.comments'
                ]
            ],
            \SpAnjaan\BlogPortal\ReportWidgets\PostsList::class => [
                'label'         => 'spanjaan.blogportal::lang.widgets.posts_list.label',
                'context'       => 'dashboard',
                'permission'    => [
                    'winter.blog.access_other_posts'
                ]
            ],
            \SpAnjaan\BlogPortal\ReportWidgets\PostsStatistics::class => [
                'label'         => 'spanjaan.blogportal::lang.widgets.posts_statistics.label',
                'context'       => 'dashboard',
                'permission'    => [
                    'winter.blog.access_other_posts'
                ]
            ],
        ];
    }
}
