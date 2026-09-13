<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'domain_id',
        'intitule',
        'ordre',
        'type',
        'options',
        'question_code',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function responses()
    {
        return $this->hasMany(Responses::class);
    }
}
