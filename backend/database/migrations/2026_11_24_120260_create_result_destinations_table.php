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
        Schema::create('result_destinations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('result_id')
                ->constrained('meeting_results')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('category')->nullable();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->unsignedInteger('total_time_seconds')->nullable();
            $table->unsignedInteger('max_time_seconds')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();

            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_destinations');
    }
};
