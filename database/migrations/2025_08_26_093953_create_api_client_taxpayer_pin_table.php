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
        Schema::create('api_client_taxpayer_pin', function (Blueprint $table) {
            $table->string('api_client_id');
            $table->string('taxpayer_pin_id');
            $table->foreign('api_client_id')->references('id')->on('api_clients')->onDelete('cascade');
            $table->foreign('taxpayer_pin_id')->references('id')->on('taxpayer_pins')->onDelete('cascade');
            $table->primary(['api_client_id', 'taxpayer_pin_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_client_taxpayer_pin');
    }
};
