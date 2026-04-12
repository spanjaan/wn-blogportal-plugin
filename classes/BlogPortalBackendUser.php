<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Classes;

use Backend\Models\User;
use Cms\Classes\Controller;
use Winter\Blog\Models\Post;

class BlogPortalBackendUser
{
    /** @var User */
    protected User $model;

    /**
     * Constructor
     *
     * @param User $model
     * @return void
     */
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Magic Method Caller
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        $methodName = str_replace('_', '', 'get' . ucwords($method, '_'));

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}(...$arguments);
        }

        return null;
    }

    /**
     * Get Controller Instance
     *
     * @return Controller|null
     */
    protected function getController(): ?Controller
    {
        return Controller::getController();
    }

    /**
     * Get User Profile URL
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        $ctrl = $this->getController();
        if ($ctrl instanceof Controller) {
            $authorPage = 'blog/author';

            return $ctrl->pageUrl($authorPage, [
                'id'   => $this->model->id,
                'slug' => $this->getSlug(),
            ]);
        }

        return null;
    }

    /**
     * Get Author Slug
     *
     * @return string
     */
    public function getSlug(): string
    {
        if (empty($this->model->spanjaan_blogportal_author_slug)) {
            return $this->model->login;
        }

        return $this->model->spanjaan_blogportal_author_slug;
    }

    /**
     * Get Display Name
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        if (!empty($this->model->spanjaan_blogportal_display_name)) {
            return $this->model->spanjaan_blogportal_display_name;
        }

        $name = '';
        if ($this->model->first_name) {
            $name = $this->model->first_name;
        }
        if ($this->model->last_name) {
            $name .= ($name ? ' ' : '') . $this->model->last_name;
        }
        return empty($name) ? ucfirst($this->model->login) : $name;
    }

    /**
     * Get Display Name (Alias)
     *
     * @return string
     */
    public function getDisplay(): string
    {
        return $this->getDisplayName();
    }

    /**
     * Get About Me Text
     *
     * @return string
     */
    public function getAboutMe(): string
    {
        return $this->model->spanjaan_blogportal_about_me ?? '';
    }

    /**
     * Get About Me Text (Alias)
     *
     * @return string
     */
    public function getAbout(): string
    {
        return $this->getAboutMe();
    }

    /**
     * Get Posts Count
     *
     * @return int
     */
    public function getCount(): int
    {
        return Post::where('user_id', $this->model->id)->count();
    }
}
