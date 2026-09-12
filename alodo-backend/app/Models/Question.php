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
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
