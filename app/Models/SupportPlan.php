<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'duration_days',
        'has_ticket',
        'has_sms',
    ];

    protected $casts = [
        'has_ticket' => 'boolean',
        'has_sms' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'support_plan_id');
    }

    public function activeProducts()
    {
        return $this->products()->where('is_active', true);
    }
}
