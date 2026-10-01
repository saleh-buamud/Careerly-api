<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    public function jobPosts(): HasMany
    {
        return $this->hasMany(JobPost::class, 'category_id');
    }
}
