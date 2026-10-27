<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchSynonym extends Model
{
    protected $fillable = [
        'term',
        'synonyms',
        'group_slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'synonyms' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
