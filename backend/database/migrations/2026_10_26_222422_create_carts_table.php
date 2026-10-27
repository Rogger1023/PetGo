<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable() //permiti nulo para visitante
                ->constrained('users') //chave estrangeira que relaciona user e carrinho
                ->cascadeOnDelete(); //exclui o carrinho após uma compra

            $table->char('guest_token_hash', 64)
                ->nullable()
                ->unique();

            $table->string('status', 20)->default('active');

            $table->index(['user_id', 'status']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
