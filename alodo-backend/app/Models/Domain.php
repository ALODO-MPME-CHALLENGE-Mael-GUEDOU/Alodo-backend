<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = [
        'intitule',
        'description',
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
