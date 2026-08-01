<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;

class CrmTag extends Model
{
    use HasSiteTenantScope;

    protected $table = 'crm_tags';

    protected $fillable = [
        'site_id',
        'name',
        'color',
    ];
}
