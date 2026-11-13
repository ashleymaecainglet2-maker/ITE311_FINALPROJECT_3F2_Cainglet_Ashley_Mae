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
        Schema::create('class_bookings', function (Blueprint $table) {
            $table->id();
            
            // Dito natin ikokonekta kung aling Gym Class ang binook
            $table->foreignId('gym_class_id')->constrained('gym_classes')->cascadeOnDelete();
            
            // Dito natin ikokonekta kung sinong Member ang nag-book
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            
            // Status ng booking (Halimbawa: 'booked', 'cancelled', 'attended')
            $table->string('status')->default('booked'); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_bookings');
    }
};