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

    protected $table = 'spanjaan_blogportal_sharecounts';

    protected $guarded = ['*'];

    protected $fillable = [
        'post_id',
        'facebook',
        'twitter',
        'linkedin',
        'whatsapp',
    ];

    public $rules = [
        'post_id' => 'required|exists:winter_blog_posts,id',
        'facebook' => 'integer|min:0',
        'twitter' => 'integer|min:0',
        'linkedin' => 'integer|min:0',
        'whatsapp' => 'integer|min:0',
    ];

    protected $casts = [
        'facebook' => 'integer',
        'twitter' => 'integer',
        'linkedin' => 'integer',
        'whatsapp' => 'integer',
    ];

    public $belongsTo = [
        'post' => [
            'Winter\Blog\Models\Post',
            'key' => 'post_id',
        ],
    ];

    public function incrementShareCount($platform)
    {
        if (in_array($platform, ['facebook', 'twitter', 'linkedin', 'whatsapp'])) {
            $this->$platform++;
            $this->save();
        }
    }

    public function getShareCount($platform)
    {
        return $this->$platform ?? 0;
    }
}
