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
        Schema::create('room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')
                ->constrained('rooms')
                ->cascadeOnDelete();

            $table->string('nickname', 30);
            $table->string('session_token_hash', 64)->unique();

            $table->enum('role', ['host', 'member'])->default('member');
            $table->enum('status', ['waiting', 'ready', 'left'])
                ->default('waiting');

            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'nickname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_members');
    }
};
