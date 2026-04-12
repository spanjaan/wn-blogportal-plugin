<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use Lang;
use System\Classes\PluginManager;
use Winter\Pages\Classes\Page as WinterPage;
use Winter\Pages\Classes\PageList as WinterPageList;
use Winter\Storm\Database\Model;

class BlogPortalSettings extends Model
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    /**
     * Get Default Value for Setting Key
     *
     * @param string $key
     * @return mixed
     */
    public static function defaultValue(string $key): mixed
    {
        $defaults = [
            'author_favorites'        => '1',
            'like_comment'            => '1',
            'dislike_comment'         => '1',
            'restrict_to_users'       => '0',
            'guest_comments'          => '1',
            'moderate_guest_comments' => '1',
            'moderate_user_comments'  => '0',
            'form_comment_title'      => '0',
            'form_comment_markdown'   => '1',
            'form_comment_honeypot'   => '1',
            'form_comment_captcha'    => '0',
            'form_tos_checkbox'       => '0',
            'form_tos_hide_on_user'   => '1',
            'form_tos_label'          => Lang::get('spanjaan.blogportal::lang.settings.comments.form_tos_label.default'),
            'form_tos_type'           => 'cms_page',
            'form_tos_cms_page'       => '',
            'form_tos_static_page'    => '',
        ];

        return $defaults[$key] ?? null;
    }

    /**
     * Get Cached Setting Value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getCached(string $key, mixed $default = null): mixed
    {
        $instance = self::instance();

        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $instance->get($key, self::defaultValue($key));
        }

        return self::$cache[$key] ?? $default;
    }

    /**
     * Clear Settings Cache
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /** @var array<string> */
    public $implement = ['System.Behaviors.SettingsModel'];

    /** @var string */
    public $settingsCode = 'spanjaan_blogportal_core_settings';

    /** @var string */
    public $settingsFields = 'fields.yaml';

    /**
     * Get Terms of Service Type Options
     *
     * @return array
     */
    public function getFormTosTypeOptions(): array
    {
        $options = [
            'cms_page' => Lang::get('spanjaan.blogportal::lang.settings.comments.form_tos_type.cms_page'),
        ];

        if (PluginManager::instance()->hasPlugin('Winter.Pages')) {
            $options['static_page'] = Lang::get('spanjaan.blogportal::lang.settings.comments.form_tos_type.static_page');
        }

        return $options;
    }

    /**
     * Get CMS Page Options for Terms of Service
     *
     * @return array
     */
    public function getFormTosCmsPageOptions(): array
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Get Static Page Options for Terms of Service
     *
     * @return array
     */
    public function getFormTosStaticPageOptions(): array
    {
        if (class_exists(WinterPageList::class)) {
            $activeTheme = Theme::getActiveTheme();

            if ($activeTheme) {
                $pages = new WinterPageList($activeTheme);
                return $pages->listPages()->sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
            }
        }

        return [];
    }

    /**
     * Get Terms of Service Label
     *
     * @return string
     */
    public function getTermsOfServiceLabel(): string
    {
        $label = $this->get('form_tos_label') ?? self::defaultValue('form_tos_label');
        $type = $this->get('form_tos_type') ?? 'static';

        $startSlash = strpos($label, '[');
        $endSlash = $startSlash !== false ? strpos($label, ']', $startSlash) : false;

        if ($startSlash > 0 && $endSlash !== false) {
            $append = substr($label, 0, $startSlash);
            $inner = substr($label, $startSlash + 1, $endSlash - $startSlash - 1);
            $prepend = substr($label, $endSlash + 1);

            if ($type === 'cms_page' && ($temp = $this->get('form_tos_cms_page', '')) !== '') {
                if ($page = Page::inTheme(Theme::getActiveTheme())->find($temp)) {
                    $inner = '<a href="' . $page->url . '">' . $inner . '</a>';
                }
            } elseif ($type === 'static_page' && ($temp = $this->get('form_tos_static_page', '')) !== '') {
                if ($pageUrl = WinterPage::url($temp)) {
                    $inner = '<a href="' . $pageUrl . '">' . $inner . '</a>';
                }
            }
            $label = trim($append . $inner . ' ' . $prepend);
        }

        return $label;
    }
}
