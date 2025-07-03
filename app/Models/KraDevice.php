<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // Required for UUID generation

class KraDevice extends Model
{
    use HasFactory;

    // Set primary key type to string for UUIDs
    protected $keyType = 'string';
    // Indicate that the primary key is not auto-incrementing
    public $incrementing = false;

    // Define fillable attributes for mass assignment
    protected $fillable = [
        'id', // Make 'id' fillable because we're manually generating UUIDs
        'taxpayer_pin_id',
        'kra_scu_id',
        'device_type',
        'status',
        'config',
        'last_status_check_at',
    ];

    // Define attribute casting for automatic type conversion
    protected $casts = [
        'config' => 'json', // Casts the 'config' column to a JSON array/object
        'last_status_check_at' => 'datetime',
    ];

    /**
     * The "booting" method of the model.
     * Generates a UUID for new models before they are saved.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            // Check if 'id' is already set (e.g., if manually provided)
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the taxpayer PIN that owns this KRA device.
     */
    public function taxpayerPin()
    {
        return $this->belongsTo(TaxpayerPin::class);
    }
}