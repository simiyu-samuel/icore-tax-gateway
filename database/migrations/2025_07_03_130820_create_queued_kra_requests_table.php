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
        Schema::create('queued_kra_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kra_device_id')->nullable()->constrained('kra_devices');
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions'); // Link to transaction if related
            $table->string('command_type')->index(); // e.g., SEND_RECEIPTITEM, SEND_ITEM
            $table->jsonb('data_payload'); // The JSON data needed to construct the KRA XML
            $table->enum('status', ['PENDING', 'PROCESSING', 'SUCCESS', 'FAILED'])->default('PENDING');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable(); // For exponential backoff
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
        // ...
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queued_kra_requests');
    }
};
