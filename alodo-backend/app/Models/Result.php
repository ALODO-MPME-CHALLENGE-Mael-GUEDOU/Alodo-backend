<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $hidden = ['interpretation_input'];

    protected $fillable = [
        'diagnostic_id',
        'score',
        'analysis',
        'scoring_version',
        'scoring_details',
        'interpretation_input',
        'analysis_status',
        'analysis_error',
        'analysis_provider',
        'analysis_model',
        'prompt_version',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'analysis' => 'json',
            'scoring_details' => 'array',
            'interpretation_input' => 'array',
            'score' => 'decimal:2',
            'analyzed_at' => 'datetime',
        ];
    }

    public function diagnostic()
    {
        return $this->belongsTo(Diagnostic::class);
    }
}
