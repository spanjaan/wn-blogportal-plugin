<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Classes;

use Backend\Models\User;
use Cms\Classes\Controller;
use Winter\Blog\Models\Post;

class BlogPortalBackendUser
{
    /**
     * User Model
     *
     * @var User
     */
    protected User $model;

    /**
     * Create a new BlogPost
     *
     * @param User $model
     */
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Call Dynamic Property Method
     *
     * @param string $method
     * @param ?array $arguments
     * @return void
     */
    public function __call($method, $arguments = [])
    {
        $methodName = str_replace('_', '', 'get' . ucwords($method, '_'));

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}(...$arguments);
        } else {
            return null;
        }
    }

    /**
     * Get current CMS Controller
     *
     * @return Controller|null
     */
    protected function getController(): ?Controller
    {
        return Controller::getController();
    }

    /**
     * Return Author Archive Page URL
     *
     * @return string|null
     */
    public function getUrl()
    {
        $ctrl = $this->getController();
        if ($ctrl instanceof Controller && !empty($ctrl->getLayout())) {
            return $ctrl->pageUrl($viewBag['blogportalAuthorPage'] ?? 'blog/author', [
                'id'   => $this->model->id,
                'slug' => $this->getSlug(),
            ]);
        } else {
            return null;
        }
    }

    /**
     * Return Author Slug
     *
     * @return string
     */
    public function getSlug()
    {
        if (empty($this->model->spanjaan_blogportal_author_slug)) {
            return $this->model->login;
        } else {
            return $this->model->spanjaan_blogportal_author_slug;
        }
    }

    /**
     * Return Author Display Name
     *
     * @return string
     */
    public function getDisplayName()
    {
        if (!empty($this->model->spanjaan_blogportal_display_name)) {
            return $this->model->spanjaan_blogportal_display_name;
        }

        $name = '';
        if ($this->model->first_name) {
            $name = $this->model->first_name;
        }
        if ($this->model->last_name) {
            $name = ($this->model->last_name ? ' ' : '') . $this->model->first_name;
        }
        return empty($name) ? ucfirst($this->model->login) : $name;
    }

    /**
     * Return Author Display Name (alias)
     *
     * @return string
     */
    public function getDisplay()
    {
        return $this->getDisplayName();
    }

    /**
     * Return Author About Me Text
     *
     * @return string
     */
    public function getAboutMe()
    {
        return $this->model->spanjaan_blogportal_about_me;
    }

    /**
     * Return Author About Me Text (alias)
     *
     * @return string
     */
    public function getAbout()
    {
        return $this->getAboutMe();
    }

    /**
     * Return Author Post Count
     *
     * @return string
     */
    public function getCount()
    {
        return Post::where('user_id', $this->model->id)->count();
    }
}
