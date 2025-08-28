<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Str; // Import Str facade

class TaxpayerPin extends Model
{
    use HasFactory;

    public $incrementing = false; // Disable auto-incrementing for UUIDs
    protected $keyType = 'string'; // Set key type to string for UUIDs
    protected $fillable = ['id', 'pin', 'name', 'address', 'is_active']; // Add 'id' to fillable

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function kraDevices()
    {
        return $this->hasMany(KraDevice::class);
    }
    public function apiClients()
    {
        return $this->belongsToMany(ApiClient::class);
    }
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
