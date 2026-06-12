<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeMarketplace extends Model
{
    protected $table = 'theme_marketplace';

    protected $fillable = [
        'theme_id',
        'marketplace_description',
        'screenshots',
        'requirements',
        'version_notes',
        'is_featured',
        'sales_count',
    ];

    protected $casts = [
        'screenshots'  => 'array',
        'requirements' => 'array',
        'is_featured'  => 'boolean',
        'sales_count'  => 'integer',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }
}
