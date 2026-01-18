<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienceMetricsDaily extends Model
{
    protected $table = 'experience_metrics_daily';
    public $timestamps = false;

    protected $fillable = [
        'experience_id',
        'date',
        'saves',
        'unsaves',
        'views',
        'shares',
        'clicks',
        'reviews',
        'plan_adds',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'saves' => 'integer',
            'unsaves' => 'integer',
            'views' => 'integer',
            'shares' => 'integer',
            'clicks' => 'integer',
            'reviews' => 'integer',
            'plan_adds' => 'integer',
        ];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
