<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class PaymentRequest extends Model
{
    use HasUuid;
    use HasFactory;

    protected $table = 'payment_requests';
    //worksaar start
    protected $casts = [
        'merchantFees_ndasenda' => 'float',
        'customerFees_ndasenda' => 'float',
        'paidDate_ndasenda' => 'datetime',
        'createdDate_ndasenda' => 'datetime',
    ];
    //worksaar end
}
