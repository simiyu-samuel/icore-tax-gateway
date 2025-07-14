<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TaxpayerPin extends Model
{
    use HasFactory;
    protected $fillable = ['pin', 'name', 'address', 'is_active'];

    public function kraDevices()
    {
        return $this->hasMany(KraDevice::class);
    }
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}