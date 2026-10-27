<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->string('postal_code', 9);
            $table->string('street', 180);
            $table->string('number', 20);
            $table->string('complement', 120)->nullable();
            $table->string('neighborhood', 100);
            $table->string('city', 100);
            $table->char('state', 2);
            $table->string('reference', 255)->nullable();

            $table->boolean('is_default')->default(false);

            $table->index(['user_id', 'is_default']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
