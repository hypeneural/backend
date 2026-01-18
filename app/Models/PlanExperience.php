<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanExperience extends Model
{
    protected $table = 'plan_experiences';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'plan_id',
        'experience_id',
        'order',
        'time_slot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
