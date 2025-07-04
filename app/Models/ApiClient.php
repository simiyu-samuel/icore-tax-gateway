<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
     * Note: For production, store hashed API keys and compare using `Hash::check`.
     * For simplicity in dev, we store plain here.
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        return static::where('api_key', $apiKey)->where('is_active', true)->first();
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