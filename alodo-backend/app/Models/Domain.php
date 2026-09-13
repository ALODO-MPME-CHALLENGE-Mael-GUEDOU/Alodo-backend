<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = [
        'intitule',
        'description',
        'ordre',
        'is_scored',
    ];

    protected function casts(): array
    {
        return [
            'is_scored' => 'boolean',
        ];
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
