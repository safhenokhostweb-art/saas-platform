<?php
namespace App\Models\Infrastructure;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InfraIncidentNote extends Model
{
    protected $table = 'infra_incident_notes';

    protected $fillable = ['incident_id', 'user_id', 'note', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function incident()
    {
        return $this->belongsTo(InfraIncident::class, 'incident_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
