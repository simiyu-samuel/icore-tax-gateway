<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ...
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary(); // ICORE's internal gatewayTransactionId
            $table->foreignUuid('kra_device_id')->constrained('kra_devices');
            $table->foreignId('taxpayer_pin_id')->constrained('taxpayer_pins'); // Denormalized for easier querying
            $table->string('internal_receipt_number')->index(); // POS/ERP's transaction ID
            $table->enum('receipt_type', ['NORMAL', 'COPY', 'TRAINING', 'PROFORMA']);
            $table->enum('transaction_type', ['SALE', 'CREDIT_NOTE', 'DEBIT_NOTE']);
            $table->string('kra_scu_id'); // KRA's device ID for this transaction
            $table->string('kra_receipt_label'); // NS, NC, etc.
            $table->string('kra_cu_invoice_number'); // KRACU0100000001/152
            $table->string('kra_digital_signature', 255);
            $table->string('kra_internal_data', 255);
            $table->string('kra_qr_code_url', 500);
            $table->jsonb('request_payload'); // Original JSON from POS/ERP
            $table->jsonb('response_payload')->nullable(); // Processed KRA response
            $table->text('raw_kra_request_xml')->nullable(); // For audit/debugging
            $table->text('raw_kra_response_xml')->nullable(); // For audit/debugging
            $table->enum('journal_status', ['PENDING', 'QUEUED', 'COMPLETED', 'FAILED'])->default('PENDING'); // Status of async journaling to KRA
            $table->string('journal_error_message')->nullable();
            $table->timestamp('kra_timestamp'); // Date and time stamped by OSCU/VSCU
            $table->timestamps();

            $table->unique(['kra_device_id', 'internal_receipt_number', 'receipt_type', 'transaction_type'], 'unique_kra_device_internal_txn');
        });
        // ...
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
