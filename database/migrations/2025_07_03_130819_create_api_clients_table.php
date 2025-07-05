<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Use UUIDs for client IDs
            $table->string('name');
            $table->string('api_key', 64)->unique(); // Hashed or plain, stored securely
            $table->boolean('is_active')->default(true);
            $table->string('allowed_taxpayer_pins', 500)->nullable(); // Comma-separated list or JSON for complex permissions
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('is_active');
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};