<?php

namespace App\Models;

use App\Enums\EmployerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployerProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employer_type',
        'organization_name',
        'description',
        'logo',
        'address',
        'website',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employer_type' => EmployerType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
