<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteUpdate extends Model
{
    protected $fillable = ['site_id', 'version_label', 'distributed_at', 'notes'];

    protected function casts(): array
    {
        return [
            'distributed_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
