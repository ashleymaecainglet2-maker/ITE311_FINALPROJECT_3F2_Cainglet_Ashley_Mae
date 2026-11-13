<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            $table->dropForeign(['gym_id']);
            $table->dropColumn('gym_id');
        });
    }
};