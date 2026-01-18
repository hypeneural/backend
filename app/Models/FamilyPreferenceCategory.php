<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyPreferenceCategory extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'family_id',
        'category_id',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
