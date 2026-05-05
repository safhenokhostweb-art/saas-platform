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
}
