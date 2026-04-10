<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Model;

/**
 * Sharecount Model
 */
class Sharecount extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * Table associated with this Model
     *
     * @var string
     */
    protected $table = 'spanjaan_blogportal_sharecounts';

    /**
     * Disable timestamps — table has no created_at/updated_at columns
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Guarded Model attributes
     *
     * @var array
     */
    protected $guarded = ['*'];

    /**
     * Fillable Model attributes
     *
     * @var array
     */
    protected $fillable = [
        'post_id',
        'facebook',
        'twitter',
        'linkedin',
        'whatsapp',
    ];

    /**
     * Supported share platforms
     *
     * @var array
     */
    protected const PLATFORMS = [
        'facebook',
        'twitter',
        'linkedin',
        'whatsapp',
    ];

    /**
     * Model Validation Rules
     *
     * @var array
     */
    public $rules = [
        'post_id'  => 'required|exists:winter_blog_posts,id',
        'facebook' => 'nullable|integer|min:0',
        'twitter'  => 'nullable|integer|min:0',
        'linkedin' => 'nullable|integer|min:0',
        'whatsapp' => 'nullable|integer|min:0',
    ];

    /**
     * Attribute Casts
     *
     * @var array
     */
    protected $casts = [
        'facebook' => 'integer',
        'twitter'  => 'integer',
        'linkedin' => 'integer',
        'whatsapp' => 'integer',
    ];

    /**
     * BelongsTo Relationships
     *
     * @var array
     */
    public $belongsTo = [
        'post' => [
            'Winter\Blog\Models\Post',
            'key' => 'post_id',
        ],
    ];

    /**
     * Increment share count for a given platform
     *
     * @param string $platform
     * @return bool
     */
    public function incrementShareCount(string $platform): bool
    {
        if (in_array($platform, self::PLATFORMS)) {
            $this->$platform++;
            return $this->save();
        }
        return false;
    }

    /**
     * Get share count for a given platform
     *
     * @param string $platform
     * @return int
     */
    public function getShareCount(string $platform): int
    {
        if (!in_array($platform, self::PLATFORMS)) {
            return 0;
        }
        return (int) ($this->$platform ?? 0);
    }
}