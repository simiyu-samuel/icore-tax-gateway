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
            $table->foreignId('taxpayer_pin_id')->constrained('taxpayer_pins'); // Link to our internal taxpayer
            $table->string('kra_scu_id')->unique(); // KRA's device ID (e.g., KRACU0100000001)
            $table->enum('device_type', ['OSCU', 'VSCU']);
            $table->enum('status', ['PENDING', 'ACTIVATED', 'UNAVAILABLE', 'ERROR'])->default('PENDING');
            $table->json('config')->nullable(); // JSON for VSCU local URL, firmware version, etc.
            $table->timestamp('last_status_check_at')->nullable();
            $table->timestamps();
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
