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
        Schema::create('kra_devices', function (Blueprint $table) {
            $table->uuid('id')->primary(); // ICORE's internal gatewayDeviceId
            $table->string('taxpayer_pin_id'); // Link to our internal taxpayer
            $table->foreign('taxpayer_pin_id')->references('id')->on('taxpayer_pins')->onDelete('cascade');
            $table->string('kra_scu_id')->unique();
            $table->enum('device_type', ['OSCU', 'VSCU']);
            $table->enum('status', ['PENDING', 'ACTIVATED', 'UNAVAILABLE', 'ERROR'])->default('PENDING');
            $table->json('config')->nullable(); // JSON for VSCU local URL, firmware version, etc.
            $table->timestamp('last_status_check_at')->nullable();
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('taxpayer_pin_id');
            $table->index('status');
            $table->index('device_type');
            $table->index('last_status_check_at');
        });
        // ...
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kra_devices');
    }
};
