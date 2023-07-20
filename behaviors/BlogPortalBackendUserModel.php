<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Behaviors;

use Backend\Models\User;
use Winter\Storm\Extension\ExtensionBase;
use SpAnjaan\BlogPortal\Classes\BlogPortalBackendUser;

class BlogPortalBackendUserModel extends ExtensionBase
{
    /**
     * Parent Post Model
     *
     * @var User
     */
    protected User $model;

    /**
     * BlogPortal Post Model DataSet
     *
     * @var ?BlogPortalBackendUser
     */
    protected ?BlogPortalBackendUser $blogportalSet;

    /**
     * Constructor
     *
     * @param User $model
     */
    public function __construct(User $model)
    {
        $this->model = $model;

        // Deprecated Methods
        $model->addDynamicMethod('blogportal_display', fn () => $this->getBlogportalAttribute()->display());
        $model->addDynamicMethod('blogportal_slug', fn () => $this->getBlogportalAttribute()->slug());
        $model->addDynamicMethod('blogportal_about', fn () => $this->getBlogportalAttribute()->about());
    }

    /**
     * Get main BlogPortal Space
     *
     * @return BlogPortalBackendUser
     */
    public function getBlogportalAttribute()
    {
        if (empty($this->blogportalSet)) {
            $this->blogportalSet = new BlogPortalBackendUser($this->model);
        }
        return $this->blogportalSet;
    }
}
