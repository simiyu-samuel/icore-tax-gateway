<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxpayerPin extends Model
{
    use HasFactory;
    protected $fillable = ['pin', 'name', 'address', 'is_active'];

    public function kraDevices()
    {
        return $this->hasMany(KraDevice::class);
    }
}