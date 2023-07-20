<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal;

use Backend;
use Event;
use Exception;
use Backend\Controllers\Users as BackendUsers;
use Backend\Facades\BackendAuth;
use Backend\Models\User as BackendUser;
use Backend\Widgets\Lists;
use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Winter\Blog\Controllers\Posts;
use Winter\Blog\Models\Post;
use SpAnjaan\BlogPortal\Behaviors\BlogPortalBackendUserModel;
use SpAnjaan\BlogPortal\Behaviors\BlogPortalPostModel;
use SpAnjaan\BlogPortal\Models\Comment;
use SpAnjaan\BlogPortal\Models\Visitor;
use Symfony\Component\Yaml\Yaml;
use System\Classes\PluginBase;

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
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

        // Extend available sorting options
        Post::$allowedSortingOptions['spanjaan_blogportal_views asc'] = 'spanjaan.blogportal::lang.sorting.blogportal_views_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_views desc'] = 'spanjaan.blogportal::lang.sorting.blogportal_views_desc';
        Post::$allowedSortingOptions['spanjaan_blogportal_unique_views asc'] = 'spanjaan.blogportal::lang.sorting.blogportal_unique_views_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_unique_views desc'] = 'spanjaan.blogportal::lang.sorting.blogportal_unique_views_desc';
        Post::$allowedSortingOptions['spanjaan_blogportal_comments_count asc'] = 'spanjaan.blogportal::lang.sorting.blogportal_comments_count_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_comments_count desc'] = 'spanjaan.blogportal::lang.sorting.blogportal_comments_count_desc';
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        // Add side menuts to Winter.Blog
        Event::listen('backend.menu.extendItems', function ($manager) {
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
                    'icon'          => 'icon-comment',
                    'code'          => 'spanjaan-blogportal-comments',
                    'owner'         => 'SpAnjaan.BlogPortal',
                    'url'           => Backend::url('spanjaan/blogportal/comments'),
                    'counter'       => Comment::where('status', 'pending')->count(),
                    'permissions'   => [
                        'spanjaan.blogportal.comments'
                    ]
                ]
            ]);
        });

        // Extend Richeditor in blog content Field.
        Event::listen('backend.form.extendFieldsBefore', function ($widget) {
            if (!($widget->getController() instanceof \Winter\Blog\Controllers\Posts
                && $widget->model instanceof \Winter\Blog\Models\Post)
            ) {
                return;
            }

            $widget->tabs['fields']['content']['type'] = 'richeditor';
        });

        // Collect (Unique) Views
        Event::listen('cms.page.end', function (Controller $ctrl) {
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
                    $post->spanjaan_blogportal_unique_views = is_numeric($post->spanjaan_blogportal_unique_views) ? $post->spanjaan_blogportal_unique_views+1 : 1;
                }
                $visitor->markAsSeen($post);
            }

            if ($guest) {
                $post->spanjaan_blogportal_views = is_numeric($post->spanjaan_blogportal_views) ? $post->spanjaan_blogportal_views+1 : 1;

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

        // Implement custom Models
        Post::extend(function ($model) {
            $model->implement[] = BlogPortalPostModel::class;
        });

        BackendUser::extend(function ($model) {
            $model->implement[] = BlogPortalBackendUserModel::class;
        });


        // Extend Form Fields on Posts Controller
        Posts::extendFormFields(function ($form, $model, $context) {
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
                        'open' => 'spanjaan.blogportal::lang.model.comments.post_mode.open',
                        'restricted' => 'spanjaan.blogportal::lang.model.comments.post_mode.restricted',
                        'private' => 'spanjaan.blogportal::lang.model.comments.post_mode.private',
                        'closed' => 'spanjaan.blogportal::lang.model.comments.post_mode.closed',
                    ],
                    'permissions'   => ['spanjaan.blogportal.comments.access_comments_settings']
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
            if (!$model instanceof Post) {
                return;
            }

            $list->addColumns([
                'spanjaan_blogportal_views' => [
                    'label' => 'spanjaan.blogportal::lang.model.visitors.views',
                    'type' => 'number',
                    'select' => 'concat(winter_blog_posts.spanjaan_blogportal_views, " / ", winter_blog_posts.spanjaan_blogportal_unique_views)',
                    'align' => 'left'
                ]
            ]);
        });

        // Add Posts Filter Scope
        Posts::extendListFilterScopes(function ($filter) {
            $filter->addScopes([
                'spanjaan_blogportal_tags' => [
                    'label' => 'spanjaan.blogportal::lang.model.tags.label',
                    'modelClass' => 'SpAnjaan\BlogPortal\Models\Tag',
                    'nameFrom' => 'slug',
                    'scope' => 'FilterTags'
                ]
            ]);
        });

        // Extend Backend Users Controller
        BackendUsers::extendFormFields(function ($form, $model, $context) {
            if (!$model instanceof BackendUser) {
                return;
            }

            // Add Display Name
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
            \SpAnjaan\BlogPortal\Components\PostsByAuthor::class => 'blogportalPostsByAuthor',
            \SpAnjaan\BlogPortal\Components\PostsByCommentCount::class => 'blogportalPostsByCommentCount',
            \SpAnjaan\BlogPortal\Components\PostsByDate::class => 'blogportalPostsByDate',
            \SpAnjaan\BlogPortal\Components\PostsByTag::class => 'blogportalPostsByTag',
            \SpAnjaan\BlogPortal\Components\CommentList::class => 'blogportalCommentList',
            \SpAnjaan\BlogPortal\Components\CommentSection::class => 'blogportalCommentSection',
            \SpAnjaan\BlogPortal\Components\Tags::class => 'blogportalTags',
            \SpAnjaan\BlogPortal\Components\PopularPosts::class => 'popularPosts',
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
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.access_comments',
                'comment' => 'spanjaan.blogportal::lang.permissions.access_comments_comment',
            ],
            'spanjaan.blogportal.comments.access_comments_settings' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.manage_post_settings'
            ],
            'spanjaan.blogportal.comments.moderate_comments' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.moderate_comments'
            ],
            'spanjaan.blogportal.comments.delete_comments' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.delete_commpents'
            ],
            'spanjaan.blogportal.tags' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.access_tags',
                'comment' => 'spanjaan.blogportal::lang.permissions.access_tags_comment',
            ],
            'spanjaan.blogportal.tags.promoted' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.promote_tags'
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
                'label' => 'spanjaan.blogportal::lang.widgets.comments_list.label',
                'context' => 'dashboard',
                'permission' => [
                    'winter.blog.access_other_posts',
                    'spanjaan.blogportal.comments'
                ]
            ],
            \SpAnjaan\BlogPortal\ReportWidgets\PostsList::class => [
                'label' => 'spanjaan.blogportal::lang.widgets.posts_list.label',
                'context' => 'dashboard',
                'permission' => [
                    'winter.blog.access_other_posts'
                ]
            ],
            \SpAnjaan\BlogPortal\ReportWidgets\PostsStatistics::class => [
                'label' => 'spanjaan.blogportal::lang.widgets.posts_statistics.label',
                'context' => 'dashboard',
                'permission' => [
                    'winter.blog.access_other_posts'
                ]
            ],
        ];
    }
}
