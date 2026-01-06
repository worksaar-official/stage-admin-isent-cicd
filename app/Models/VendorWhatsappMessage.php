<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorWhatsappMessage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'vendor_id' => 'integer',
        'status' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
