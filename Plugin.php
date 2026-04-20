<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal;

use Backend;
use Backend\Controllers\Users as BackendUsers;
use Backend\Facades\BackendAuth;
use Backend\Models\User as BackendUser;
use Backend\Widgets\Lists;
use Cms\Classes\Controller;
use Event;
use SpAnjaan\BlogPortal\Behaviors\BlogPortalBackendUserModel;
use SpAnjaan\BlogPortal\Behaviors\BlogPortalPostModel;
use SpAnjaan\BlogPortal\Jobs\RecordViewJob;
use SpAnjaan\BlogPortal\Models\Comment;
use SpAnjaan\BlogPortal\Models\Visitor;
use System\Classes\PluginBase;
use Winter\Blog\Controllers\Posts;
use Winter\Blog\Models\Post;

class Plugin extends PluginBase
{
    /** @var array<string> */
    public $require = [
        'Winter.Blog',
    ];

    private const MENU_EVENT = 'backend.menu.extendItems';
    private const PAGE_EVENT = 'cms.page.end';

    /**
     * Plugin Details
     *
     * @return array
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'spanjaan.blogportal::lang.plugin.name',
            'description' => 'spanjaan.blogportal::lang.plugin.description',
            'author'      => 'S.p. Anjaan',
            'icon'        => 'icon-tags',
            'homepage'    => 'https://github.com/spanjaan/blogportal',
            'version'     => '2.0.0',
        ];
    }

    /**
     * Register Method
     *
     * @return void
     */
    public function register(): void
    {
        Post::$allowedSortingOptions['spanjaan_blogportal_views asc'] =
            'spanjaan.blogportal::lang.sorting.blogportal_views_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_views desc'] =
            'spanjaan.blogportal::lang.sorting.blogportal_views_desc';
        Post::$allowedSortingOptions['spanjaan_blogportal_unique_views asc'] =
            'spanjaan.blogportal::lang.sorting.blogportal_unique_views_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_unique_views desc'] =
            'spanjaan.blogportal::lang.sorting.blogportal_unique_views_desc';
        Post::$allowedSortingOptions['spanjaan_blogportal_comments_count asc'] =
            'spanjaan.blogportal::lang.sorting.blogportal_comments_count_asc';
        Post::$allowedSortingOptions['spanjaan_blogportal_comments_count desc'] =
            'spanjaan.blogportal::lang.sorting.blogportal_comments_count_desc';
    }

    /**
     * Boot Method
     *
     * @return void
     */
    public function boot(): void
    {
        $this->extendBackendMenu();
        $this->collectUniqueViews();
        $this->implementCustomModels();
    }

    /**
     * Extend Backend Menu
     *
     * @return void
     */
    private function extendBackendMenu(): void
    {
        Event::listen(self::MENU_EVENT, function ($manager) {
            $manager->addSideMenuItems('Winter.Blog', 'blog', [
                'spanjaan_blogportal_tags' => [
                    'label'       => 'spanjaan.blogportal::lang.model.tags.label',
                    'icon'        => 'icon-tags',
                    'code'        => 'spanjaan-blogportal-tags',
                    'owner'       => 'SpAnjaan.BlogPortal',
                    'url'         => Backend::url('spanjaan/blogportal/tags'),
                    'permissions' => [
                        'spanjaan.blogportal.tags',
                    ],
                ],
                'spanjaan_blogportal_comments' => [
                    'label'       => 'spanjaan.blogportal::lang.model.comments.label',
                    'icon'        => 'icon-message',
                    'code'        => 'spanjaan-blogportal-comments',
                    'owner'       => 'SpAnjaan.BlogPortal',
                    'url'         => Backend::url('spanjaan/blogportal/comments'),
                    'counter'     => Comment::where('status', 'pending')->count(),
                    'permissions' => [
                        'spanjaan.blogportal.comments',
                    ],
                ],
                'spanjaan_blogportal_sharecounts' => [
                    'label'       => 'spanjaan.blogportal::lang.model.sharecounts.label',
                    'icon'        => 'icon-share-nodes',
                    'code'        => 'spanjaan-blogportal-sharecounts',
                    'owner'       => 'SpAnjaan.BlogPortal',
                    'url'         => Backend::url('spanjaan/blogportal/sharecounts'),
                    'permissions' => [
                        'spanjaan.blogportal.sharecounts',
                    ],
                ],
            ]);
        });
    }

    /**
     * Collect Unique Views
     *
     * @return void
     */
    private function collectUniqueViews(): void
    {
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
                $visitor->markAsSeen($post);

                if ($guest) {
                    RecordViewJob::dispatch(
                        (int) $post->id,
                        (int) $visitor->id,
                        true
                    );
                }
            }

            if ($guest) {
                RecordViewJob::dispatch(
                    (int) $post->id,
                    (int) $visitor->id,
                    false
                );
            }
        });
    }

    /**
     * Implement Custom Models
     *
     * @return void
     */
    private function implementCustomModels(): void
    {
        Post::extend(function ($model) {
            $model->implement[] = BlogPortalPostModel::class;
        });

        BackendUser::extend(function ($model) {
            $model->implement[] = BlogPortalBackendUserModel::class;
        });

        Posts::extendFormFields(function ($form, $model, $context) {
            if (!$model instanceof Post) {
                return;
            }

            $form->addTabFields([
                'spanjaan_blogportal_comment_visible' => [
                    'tab'         => 'spanjaan.blogportal::lang.model.comments.label',
                    'type'        => 'switch',
                    'label'       => 'spanjaan.blogportal::lang.model.comments.post_visibility.label',
                    'comment'     => 'spanjaan.blogportal::lang.model.comments.post_visibility.comment',
                    'span'        => 'left',
                    'permissions' => ['spanjaan.blogportal.comments.access_comments_settings'],
                ],
                'spanjaan_blogportal_comment_mode' => [
                    'tab'         => 'spanjaan.blogportal::lang.model.comments.label',
                    'type'        => 'dropdown',
                    'label'       => 'spanjaan.blogportal::lang.model.comments.post_mode.label',
                    'comment'     => 'spanjaan.blogportal::lang.model.comments.post_mode.comment',
                    'showSearch'  => false,
                    'span'        => 'left',
                    'options'     => [
                        'open'       => 'spanjaan.blogportal::lang.model.comments.post_mode.open',
                        'restricted' => 'spanjaan.blogportal::lang.model.comments.post_mode.restricted',
                        'private'    => 'spanjaan.blogportal::lang.model.comments.post_mode.private',
                        'closed'     => 'spanjaan.blogportal::lang.model.comments.post_mode.closed',
                    ],
                    'permissions' => ['spanjaan.blogportal.comments.access_comments_settings'],
                ],
            ]);

            $form->addTabFields([
                'spanjaan_blogportal_tags' => [
                    'label'       => 'spanjaan.blogportal::lang.model.tags.label',
                    'mode'        => 'relation',
                    'tab'         => 'winter.blog::lang.post.tab_categories',
                    'type'        => 'taglist',
                    'nameFrom'    => 'slug',
                    'permissions' => ['spanjaan.blogportal.tags'],
                ],
            ]);
        });

        Posts::extendListColumns(function (Lists $list, $model) {
            if (!$model instanceof Post) {
                return;
            }

            $list->addColumns([
                'spanjaan_blogportal_views' => [
                    'label'  => 'spanjaan.blogportal::lang.model.visitors.views',
                    'type'   => 'number',
                    'select' => 'concat(winter_blog_posts.spanjaan_blogportal_views, " / ", winter_blog_posts.spanjaan_blogportal_unique_views)',
                    'align'  => 'left',
                ],
            ]);
        });

        Posts::extendListFilterScopes(function ($filter) {
            $filter->addScopes([
                'spanjaan_blogportal_tags' => [
                    'label'      => 'spanjaan.blogportal::lang.model.tags.label',
                    'modelClass' => 'SpAnjaan\BlogPortal\Models\Tag',
                    'nameFrom'   => 'slug',
                    'scope'      => 'filterTags',
                ],
            ]);
        });

        BackendUsers::extendFormFields(function ($form, $model, $context) {
            if (!$model instanceof BackendUser) {
                return;
            }

            $form->addTabFields([
                'spanjaan_blogportal_display_name' => [
                    'label'       => 'spanjaan.blogportal::lang.model.users.displayName',
                    'description' => 'spanjaan.blogportal::lang.model.users.displayNameDescription',
                    'tab'         => 'backend::lang.user.account',
                    'type'        => 'text',
                    'span'        => 'left',
                ],
                'spanjaan_blogportal_author_slug' => [
                    'label'       => 'spanjaan.blogportal::lang.model.users.authorSlug',
                    'description' => 'spanjaan.blogportal::lang.model.users.authorSlugDescription',
                    'tab'         => 'backend::lang.user.account',
                    'type'        => 'text',
                    'span'        => 'right',
                ],
                'spanjaan_blogportal_about_me' => [
                    'label'       => 'spanjaan.blogportal::lang.model.users.aboutMe',
                    'description' => 'spanjaan.blogportal::lang.model.users.aboutMeDescription',
                    'tab'         => 'backend::lang.user.account',
                    'type'        => 'textarea',
                ],
            ]);
        });
    }

    /**
     * Register Components
     *
     * @return array
     */
    public function registerComponents(): array
    {
        return [
            \SpAnjaan\BlogPortal\Components\PostsByAuthor::class       => 'blogportalPostsByAuthor',
            \SpAnjaan\BlogPortal\Components\PostsByCommentCount::class => 'blogportalPostsByCommentCount',
            \SpAnjaan\BlogPortal\Components\PostsByDate::class         => 'blogportalPostsByDate',
            \SpAnjaan\BlogPortal\Components\PostsByTag::class           => 'blogportalPostsByTag',
            \SpAnjaan\BlogPortal\Components\CommentList::class          => 'blogportalCommentList',
            \SpAnjaan\BlogPortal\Components\CommentSection::class       => 'blogportalCommentSection',
            \SpAnjaan\BlogPortal\Components\Tags::class                 => 'blogportalTags',
            \SpAnjaan\BlogPortal\Components\PopularPosts::class         => 'blogportalPopularPosts',
            \SpAnjaan\BlogPortal\Components\ArchiveLinks::class         => 'blogportalArchiveLinks',
            \SpAnjaan\BlogPortal\Components\ShareButtons::class         => 'blogportalShareButtons',
        ];
    }

    /**
     * Register Permissions
     *
     * @return array
     */
    public function registerPermissions(): array
    {
        return [
            'spanjaan.blogportal.comments' => [
                'tab'     => 'winter.blog::lang.blog.tab',
                'label'   => 'spanjaan.blogportal::lang.permissions.access_comments',
                'comment' => 'spanjaan.blogportal::lang.permissions.access_comments_comment',
            ],
            'spanjaan.blogportal.comments.access_comments_settings' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.manage_post_settings',
            ],
            'spanjaan.blogportal.comments.moderate_comments' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.moderate_comments',
            ],
            'spanjaan.blogportal.comments.delete_comments' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.delete_comments',
            ],
            'spanjaan.blogportal.tags' => [
                'tab'     => 'winter.blog::lang.blog.tab',
                'label'   => 'spanjaan.blogportal::lang.permissions.access_tags',
                'comment' => 'spanjaan.blogportal::lang.permissions.access_tags_comment',
            ],
            'spanjaan.blogportal.tags.promoted' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.promote_tags',
            ],
            'spanjaan.blogportal.sharecounts' => [
                'tab'   => 'winter.blog::lang.blog.tab',
                'label' => 'spanjaan.blogportal::lang.permissions.sharecounts',
            ],
        ];
    }

    /**
     * Register Navigation
     *
     * @return array
     */
    public function registerNavigation(): array
    {
        return [];
    }

    /**
     * Register Settings
     *
     * @return array
     */
    public function registerSettings(): array
    {
        return [
            'spanjaan_blogportal_config' => [
                'label'       => 'spanjaan.blogportal::lang.settings.config.label',
                'description' => 'spanjaan.blogportal::lang.settings.config.description',
                'category'    => 'winter.blog::lang.blog.menu_label',
                'icon'        => 'icon-pencil-square-o',
                'class'       => 'SpAnjaan\BlogPortal\Models\BlogPortalSettings',
                'order'       => 500,
                'keywords'    => 'blog post tag comments',
                'permissions' => ['winter.blog.manage_settings'],
                'size'        => 'adaptive',
            ],
        ];
    }

    /**
     * Register Report Widgets
     *
     * @return array
     */
    public function registerReportWidgets(): array
    {
        return [
            \SpAnjaan\BlogPortal\ReportWidgets\CommentsList::class => [
                'label'      => 'spanjaan.blogportal::lang.widgets.comments_list.label',
                'context'    => 'dashboard',
                'permission' => [
                    'winter.blog.access_other_posts',
                    'spanjaan.blogportal.comments',
                ],
            ],
            \SpAnjaan\BlogPortal\ReportWidgets\PostsList::class => [
                'label'      => 'spanjaan.blogportal::lang.widgets.posts_list.label',
                'context'    => 'dashboard',
                'permission' => [
                    'winter.blog.access_other_posts',
                ],
            ],
            \SpAnjaan\BlogPortal\ReportWidgets\PostsStatistics::class => [
                'label'      => 'spanjaan.blogportal::lang.widgets.posts_statistics.label',
                'context'    => 'dashboard',
                'permission' => [
                    'winter.blog.access_other_posts',
                ],
            ],
        ];
    }
}
