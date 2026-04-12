<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Models;

use Model;
use Winter\Storm\Database\Traits\Validation;

class Sharecount extends Model
{
    use Validation;

    /** @var string */
    protected $table = 'spanjaan_blogportal_sharecounts';

    /** @var bool */
    public $timestamps = false;

    /** @var array<string> */
    protected $guarded = ['*'];

    /** @var array<string> */
    protected $fillable = [
        'post_id',
        'facebook',
        'twitter',
        'linkedin',
        'whatsapp',
    ];

    /** @var array<string> */
    protected const PLATFORMS = [
        'facebook',
        'twitter',
        'linkedin',
        'whatsapp',
    ];

    /** @var array<string, mixed> */
    public $rules = [
        'post_id'  => 'required|exists:winter_blog_posts,id',
        'facebook' => 'nullable|integer|min:0',
        'twitter'  => 'nullable|integer|min:0',
        'linkedin' => 'nullable|integer|min:0',
        'whatsapp' => 'nullable|integer|min:0',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'facebook' => 'integer',
        'twitter'  => 'integer',
        'linkedin' => 'integer',
        'whatsapp' => 'integer',
    ];

    /** @var array<string, array<string, mixed>> */
    public $belongsTo = [
        'post' => [
            'Winter\Blog\Models\Post',
            'key' => 'post_id',
        ],
    ];

    /**
     * Increment Share Count for Platform
     *
     * @param string $platform
     * @return bool
     */
    public function incrementShareCount(string $platform): bool
    {
        if (in_array($platform, self::PLATFORMS, true)) {
            $this->$platform++;
            return $this->save();
        }
        return false;
    }

    /**
     * Get Share Count for Platform
     *
     * @param string $platform
     * @return int
     */
    public function getShareCount(string $platform): int
    {
        if (!in_array($platform, self::PLATFORMS, true)) {
            return 0;
        }
        return (int) ($this->$platform ?? 0);
    }
}
