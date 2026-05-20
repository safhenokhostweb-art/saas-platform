<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeRevision extends Model
{
    protected $fillable = ['theme_id', 'version', 'label', 'snapshot', 'user_id'];

    protected $casts = [
        'snapshot' => 'array',
        'version' => 'integer',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
