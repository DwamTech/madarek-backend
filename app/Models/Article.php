<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        // 'section_id',
        'issue_id',
        'title',
        'slug',
        'keywords',
        'excerpt',
        'content',
        'author_name',
        'featured_image',
        'gregorian_date',
        'hijri_date',
        'references',
        'status',
        'published_at',
        'views_count',
        'className',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'section_id' => 'integer',
        'issue_id' => 'integer',
    ];

    public function getFeaturedImageAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        }

        return null;
    }

    // public function section()
    // {
    //     return $this->belongsTo(Section::class);
    // }

    public function issue()
    {
        return $this->belongsTo(Issue::class);
    }
}
