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
        Schema::table('kra_devices', function (Blueprint $table) {
            if (Schema::hasColumn('kra_devices', 'taxpayer_pin_id')) {
                $table->foreign('taxpayer_pin_id')->references('id')->on('taxpayer_pins')->onDelete('cascade');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'taxpayer_pin_id')) {
                $table->foreign('taxpayer_pin_id')->references('id')->on('taxpayer_pins')->onDelete('cascade');
            }
        });

        Schema::table('taxpayer_pin_user', function (Blueprint $table) {
            if (Schema::hasColumn('taxpayer_pin_user', 'taxpayer_pin_id')) {
                $table->foreign('taxpayer_pin_id')->references('id')->on('taxpayer_pins')->onDelete('cascade');
            }
        });

        Schema::table('api_client_taxpayer_pin', function (Blueprint $table) {
            if (Schema::hasColumn('api_client_taxpayer_pin', 'taxpayer_pin_id')) {
                $table->foreign('taxpayer_pin_id')->references('id')->on('taxpayer_pins')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kra_devices', function (Blueprint $table) {
            $table->dropForeign(['taxpayer_pin_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['taxpayer_pin_id']);
        });

        Schema::table('taxpayer_pin_user', function (Blueprint $table) {
            $table->dropForeign(['taxpayer_pin_id']);
        });

        Schema::table('api_client_taxpayer_pin', function (Blueprint $table) {
            $table->dropForeign(['taxpayer_pin_id']);
        });
    }
};
