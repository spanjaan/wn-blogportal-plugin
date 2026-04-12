<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Cms\Classes\Controller;
use Model;
use Winter\Storm\Support\Str;

class Tag extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /** @var string */
    public $implement = ['@Winter.Translate.Behaviors.TranslatableModel'];

    /** @var string */
    public $table = 'spanjaan_blogportal_tags';

    /** @var bool */
    public $timestamps = true;

    /** @var array<string> */
    protected $guarded = ['*'];

    /** @var array<string> */
    protected $fillable = [
        'slug',
        'title',
        'description',
        'promote',
        'color',
    ];

    /** @var array<string, mixed> */
    public $attributes = [
        'color' => '#007bff',
    ];

    /** @var array<string, mixed> */
    public $rules = [
        'slug'  => 'required|between:3,64|unique:spanjaan_blogportal_tags',
        'title' => 'required|unique:spanjaan_blogportal_tags',
    ];

    /** @var array<string> */
    public $translatable = [
        'title',
        'description',
        ['slug', 'index' => true],
    ];

    /** @var array<string> */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /** @var array<string, array<string, mixed>> */
    public $belongsToMany = [
        'posts' => [
            'Winter\Blog\Models\Post',
            'table' => 'spanjaan_blogportal_tags_posts',
            'order' => 'published_at desc',
        ],
        'posts_count' => [
            'Winter\Blog\Models\Post',
            'table' => 'spanjaan_blogportal_tags_posts',
            'scope' => 'isPublished',
            'count' => true,
        ],
    ];

    /**
     * Before Save Event Handler
     *
     * @return void
     */
    public function beforeSave(): void
    {
        if ($this->isDirty('title') && empty($this->slug)) {
            $this->slug = Str::slug($this->title);
        }
    }

    /**
     * Set URL for Tag
     *
     * @param string $pageName
     * @param Controller $controller
     * @param array $params
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
     * Get Post Count Attribute
     *
     * @return int
     */
    public function getPostCountAttribute(): int
    {
        return optional($this->posts_count->first())->count ?? 0;
    }
}
