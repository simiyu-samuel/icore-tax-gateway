<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ApiClient extends Model
{
    use HasFactory;

    protected $keyType = 'string'; // UUID primary key
    public $incrementing = false;

    protected $fillable = [
        'id', 'name', 'api_key', 'is_active', 'allowed_taxpayer_pins', 'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    // Generate UUID for new clients
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    /**
     * Find a client by their API key.
     * Uses Hash::check to compare the provided key with the stored hash.
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        $clients = static::where('is_active', true)->get();
        
        foreach ($clients as $client) {
            if (Hash::check($apiKey, $client->api_key)) {
                return $client;
            }
        }
        
        return null;
    }

    /**
     * Hash the API key before saving
     */
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = Hash::make($value);
    }

    public function taxpayerPins()
    {
        // If allowed_taxpayer_pins is a comma-separated string
        return TaxpayerPin::whereIn('pin', explode(',', $this->allowed_taxpayer_pins));
    }

    /**
     * Check if this API client is allowed to access a specific taxpayer PIN.
     *
     * @param string|null $taxpayerPin
     * @return bool
     */
    public function isAllowedTaxpayerPin(?string $taxpayerPin): bool
    {
        // If no PIN is provided, allow access (for endpoints that don't require PIN)
        if (empty($taxpayerPin)) {
            return true;
        }

        // If no allowed pins are set, deny access
        if (empty($this->allowed_taxpayer_pins)) {
            return false;
        }

        // Convert comma-separated string to array and check if PIN is in the list
        $allowedPins = array_map('trim', explode(',', $this->allowed_taxpayer_pins));
        return in_array($taxpayerPin, $allowedPins);
    }
}