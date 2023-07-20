<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Lang;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Winter\Storm\Database\Model;
use Winter\Pages\Classes\Page as WinterPage;
use Winter\Pages\Classes\PageList as WinterPageList;
use System\Classes\PluginManager;

class BlogPortalSettings extends Model
{
    /**
     * Default Settings
     *
     * @param string $key
     * @return mixed
     */
    public static function defaultValue($key)
    {
        return [
            "author_favorites" => '1',
            "like_comment" => '1',
            "dislike_comment" => '1',
            "restrict_to_users" => '0',
            "guest_comments" => '1',
            "moderate_guest_comments" => '1',
            "moderate_user_comments" => '0',
            "form_comment_title" => '0',
            "form_comment_markdown" => '1',
            "form_comment_honeypot" => '1',
            "form_comment_captcha" => '0',
            "form_tos_checkbox" => '0',
            "form_tos_hide_on_user" => '1',
            "form_tos_label" => Lang::get('spanjaan.blogportal::lang.settings.comments.form_tos_label.default'),
            "form_tos_type" => 'cms_page',
            "form_tos_cms_page" => '',
            "form_tos_static_page" => '',
        ][$key] ?? null;
    }

    /**
     * Implement Interfaces
     *
     * @var array
     */
    public $implement = ['System.Behaviors.SettingsModel'];

    /**
     * Settings Mode
     *
     * @var string
     */
    public $settingsCode = 'spanjaan_blogportal_core_settings';

    /**
     * Settings Fields
     *
     * @var string
     */
    public $settingsFields = 'fields.yaml';

    /**
     * Terms of Service Type Options
     *
     * @return array
     */
    public function getFormTosTypeOptions()
    {
        $options = [
            'cms_page' => Lang::get('spanjaan.blogportal::lang.settings.comments.form_tos_type.cms_page')
        ];

        if (PluginManager::instance()->hasPlugin('Winter.Pages')) {
            $options['static_page'] = Lang::get('spanjaan.blogportal::lang.settings.comments.form_tos_type.static_page');
        }

        return $options;
    }

    /**
     * Terms of Service CMS Page Options
     *
     * @return array
     */
    public function getFormTosCmsPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Terms of Service Page Options
     *
     * @return array
     */
    public function getFormTosStaticPageOptions()
    {
        if (class_exists('WinterPageList')) {
            $activeTheme = Theme::getActiveTheme();

            if ($activeTheme) {
                $pages = new WinterPageList($activeTheme);
                return $pages->listPages()->sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
            }
        }

        return [];
    }

    /**
     * Buils up the Terms of Service Label
     *
     * @return string
     */
    public function getTermsOfServiceLabel()
    {
        $label = $this->get('form_tos_label') ?? self::defaultValue('form_tos_label');
        $type = $this->get('form_tos_type') ?? 'static';

        // Check for [] -brackets
        if (($startSlash = strpos($label, '[')) !== false) {
            $endSlash = strpos($label, ']', $startSlash);
        }

        // Replace Brackets Text
        if ($startSlash > 0 && $endSlash > 0) {
            $append = substr($label, 0, $startSlash);
            $inner = substr($label, $startSlash+1, $endSlash - $startSlash - 1);
            $prepend = substr($label, $endSlash+1);

            if ($type === 'cms_page' && ($temp = $this->get('form_tos_cms_page', '')) !== '') {
                if ($page = Page::inTheme(Theme::getActiveTheme())->find($temp)) {
                    $inner = '<a href="'. $page->url .'">'. $inner .'</a>';
                }
            } elseif ($type === 'static_page' && ($temp = $this->get('form_tos_static_page', '')) !== '') {
                if ($page_url = WinterPage::url($temp)) {
                    $inner = '<a href="'. $page_url .'">'. $inner .'</a>';
                }
            }
            $label = trim($append . $inner . ' ' . $prepend);
        }

        // Return Label
        return $label;
    }
}
