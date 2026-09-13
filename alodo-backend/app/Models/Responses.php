<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Responses extends Model
{
    protected $fillable = [
        'question_id',
        'diagnostic_id',
        'valeur',
    ];

    public function casts()
    {
        return [
            'valeur' => 'json',
        ];
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function diagnostic()
    {
        return $this->belongsTo(Diagnostic::class);
    }
}
