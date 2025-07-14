<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxpayer_pin_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('taxpayer_pin_id')->constrained('taxpayer_pins')->onDelete('cascade');
            $table->primary(['user_id', 'taxpayer_pin_id']); // Composite primary key
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxpayer_pin_user');
    }
};