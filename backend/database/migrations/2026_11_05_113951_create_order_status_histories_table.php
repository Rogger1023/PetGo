<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();

            // Pedido ao qual o evento pertence.
            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            // Usuário responsável, quando conhecido.
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Status anterior e novo status.
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            // Origem da alteração.
            $table->string('source', 50);

            // Data de criação do evento.
            $table->timestamp('created_at')->useCurrent();

            // Índice para consultas do histórico.
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
