<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'kra_device_id',
        'taxpayer_pin_id',
        'internal_receipt_number',
        'receipt_type',
        'transaction_type',
        'kra_scu_id',
        'kra_receipt_label',
        'kra_cu_invoice_number',
        'kra_digital_signature',
        'kra_internal_data',
        'kra_qr_code_url',
        'request_payload',
        'response_payload',
        'raw_kra_request_xml',
        'raw_kra_response_xml',
        'journal_status',
        'journal_error_message',
        'kra_timestamp',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'kra_timestamp' => 'datetime',
    ];

    /**
     * Get the KRA device that processed this transaction.
     */
    public function kraDevice(): BelongsTo
    {
        return $this->belongsTo(KraDevice::class);
    }

    /**
     * Get the taxpayer PIN associated with this transaction.
     */
    public function taxpayerPin(): BelongsTo
    {
        return $this->belongsTo(TaxpayerPin::class);
    }

    /**
     * Scope to filter by journal status.
     */
    public function scopeWithJournalStatus($query, string $status)
    {
        return $query->where('journal_status', $status);
    }

    /**
     * Scope to filter by receipt type.
     */
    public function scopeWithReceiptType($query, string $receiptType)
    {
        return $query->where('receipt_type', $receiptType);
    }

    /**
     * Scope to filter by transaction type.
     */
    public function scopeWithTransactionType($query, string $transactionType)
    {
        return $query->where('transaction_type', $transactionType);
    }

    /**
     * Scope to filter by KRA device.
     */
    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('kra_device_id', $deviceId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('kra_timestamp', [$startDate, $endDate]);
    }
} 