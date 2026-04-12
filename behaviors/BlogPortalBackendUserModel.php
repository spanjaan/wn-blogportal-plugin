<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Behaviors;

use Backend\Models\User;
use Winter\Storm\Extension\ExtensionBase;
use SpAnjaan\BlogPortal\Classes\BlogPortalBackendUser;

class BlogPortalBackendUserModel extends ExtensionBase
{
    /** @var User */
    protected User $model;

    /** @var BlogPortalBackendUser|null */
    protected ?BlogPortalBackendUser $blogportalSet = null;

    /**
     * Constructor
     *
     * @param User $model
     * @return void
     */
    public function __construct(User $model)
    {
        $this->model = $model;

        $model->addDynamicMethod(
            'blogportal_display',
            fn() => $this->getBlogportalAttribute()->display()
        );
        $model->addDynamicMethod(
            'blogportal_slug',
            fn() => $this->getBlogportalAttribute()->slug()
        );
        $model->addDynamicMethod(
            'blogportal_about',
            fn() => $this->getBlogportalAttribute()->about()
        );
    }

    /**
     * Get BlogPortal Attribute
     *
     * @return BlogPortalBackendUser
     */
    public function getBlogportalAttribute(): BlogPortalBackendUser
    {
        if (empty($this->blogportalSet)) {
            $this->blogportalSet = new BlogPortalBackendUser($this->model);
        }
        return $this->blogportalSet;
    }
}
