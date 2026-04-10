<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Cms\Classes\Controller;
use Model;
use Winter\Storm\Support\Str;

class Tag extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public $implement = ['@Winter.Translate.Behaviors.TranslatableModel'];

    /**
     * Table associated with this Model
     *
     * @var string
     */
    public $table = 'spanjaan_blogportal_tags';

    /**
     * Enable Modal Timestamps
     *
     * @var boolean
     */
    public $timestamps = true;

    /**
     * Guarded Model attributes
     *
     * @var array
     */
    protected $guarded = [
        '*'
    ];

    /**
     * Fillable Model attributes
     *
     * @var array
     */
    protected $fillable = [
        "slug",
        "title",
        "description",
        "promote",
        "color"
    ];

    /**
     * Default values for model fields
     *
     * @var array
     */
    public $attributes = [
        'color' => '#007bff'
    ];

    /**
     * Model Validation Rules
     *
     * @var array
     */
    public $rules = [
        'slug'  => 'required|between:3,64|unique:spanjaan_blogportal_tags',
        'title' => 'required|unique:spanjaan_blogportal_tags',
    ];

    /**
     * @var array Attributes that support translation, if available.
     */
    public $translatable = [
        'title',
        'description',
        ['slug', 'index' => true],
    ];

    /**
     * Mutable Date Attributes
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    /**
     * BelongsToMany Relationships
     *
     * @var array
     */
    public $belongsToMany = [
        'posts' => [
            'Winter\Blog\Models\Post',
            'table' => 'spanjaan_blogportal_tags_posts',
            'order' => 'published_at desc'
        ],
        'posts_count' => [
            'Winter\Blog\Models\Post',
            'table' => 'spanjaan_blogportal_tags_posts',
            'scope' => 'isPublished',
            'count' => true
        ]
    ];

    /**
     * Hook - Before Model is saved
     * Only auto-generate slug from title if slug is not already set manually.
     *
     * @return void
     */
    public function beforeSave()
    {
        if ($this->isDirty('title') && empty($this->slug)) {
            $this->slug = Str::slug($this->title);
        }
    }

    /**
     * Sets the "url" attribute with a URL to this object.
     *
     * @param string $pageName
     * @param Controller $controller
     * @param array $params Override request URL parameters
     * @return string
     */
    public function setUrl(string $pageName, Controller $controller, array $params = []): string
    {
        $params = array_merge([
            'id'   => $this->id,
            'slug' => $this->slug,
        ], $params);

        return $this->url = $controller->pageUrl($pageName, $params);
    }

    /**
     * Get Posts Count Value
     *
     * @return int
     */
    public function getPostCountAttribute(): int
    {
        return optional($this->posts_count->first())->count ?? 0;
    }
}