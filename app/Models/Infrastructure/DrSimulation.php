<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;

class DrSimulation extends Model
{
    protected $table = 'dr_simulations';

    protected $fillable = [
        'operator_id',
        'operator_name',
        'result',
        'duration_seconds',
        'estimated_restore_time',
        'estimated_rpo',
        'components',
        'notes',
    ];

    protected $casts = [
        'components' => 'array',
    ];

    public function isPass(): bool
    {
        return $this->result === 'PASS';
    }

    public function durationForHumans(): string
    {
        if (!$this->duration_seconds) {
            return '—';
        }
        if ($this->duration_seconds < 60) {
            return $this->duration_seconds . ' ثانیه';
        }
        $min = (int) ($this->duration_seconds / 60);
        $sec = $this->duration_seconds % 60;
        return $sec > 0 ? "{$min} دقیقه {$sec} ثانیه" : "{$min} دقیقه";
    }
}
