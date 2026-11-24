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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('pin', 6)->unique();
            $table->unsignedBigInteger('host_member_id')->nullable();
            $table->enum('travel_mode', ['walking', 'driving']);
            $table->enum('status', [
                'waiting', 'calculating', 'completed', 'closed'
            ])->default('waiting');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
