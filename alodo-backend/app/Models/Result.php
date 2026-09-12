<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'diagnostic_id',
        'score',
        'analysis',
    ];

    public function diagnostic()
    {
        return $this->belongsTo(Diagnostic::class);
    }
}
