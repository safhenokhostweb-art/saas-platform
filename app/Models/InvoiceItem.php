<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'quantity',
        'price_irr',
        'site_id',
        'platform_license_id',
    ];

    protected $casts = [
        'price_irr' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function platformLicense()
    {
        return $this->belongsTo(PlatformLicense::class, 'platform_license_id');
    }
}
