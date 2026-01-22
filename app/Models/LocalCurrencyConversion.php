<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property float $local_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LocalCurrencyConversion extends Model
{
    use HasFactory;

    protected $table = 'local_currency_conversion';

    protected $fillable = [
        'local_rate',
    ];
}