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
        Schema::create('gym_classes', function (Blueprint $table) {
            $table->id();
            // Gagamitin natin ito para paghiwalayin ang classes per gym (para sa Multi-tenancy)
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); 
            $table->string('name'); // Halimbawa: HIIT, Yoga, Boxing
            $table->integer('capacity'); // Maximum slots
            $table->dateTime('schedule_time'); // Kailan gaganapin
            $table->integer('duration_minutes'); // Gaano katagal
            $table->string('instructor'); // Pangalan ng instructor
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_classes');
    }
};