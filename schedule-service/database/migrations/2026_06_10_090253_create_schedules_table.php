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
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();

        $table->integer('vehicle_id');
        $table->integer('driver_id');

        $table->string('destination');

        $table->date('departure_date');
        $table->date('return_date');

        $table->string('purpose');

        $table->string('status')
              ->default('Scheduled');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
