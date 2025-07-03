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
        Schema::create('taxpayer_pins', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 20)->unique(); // KRA PIN format e.g., P000000000Z
            $table->string('name')->nullable(); // KRA company name
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxpayer_pins');
    }
};
