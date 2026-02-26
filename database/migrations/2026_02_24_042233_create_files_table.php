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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('president_name')->nullable();
            $table->string('original_name')->nullable();   // filename shown to user
            $table->string('path')->nullable();                        // storage path
            $table->string('status')->default('pending'); // completed, pending, incomplete
            $table->json('missing_fields')->nullable();
            $table->json('filled_fields')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
