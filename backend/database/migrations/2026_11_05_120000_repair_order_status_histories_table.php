<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'order_status_histories';

        // Verifica se a tabela original existe.
        if (!Schema::hasTable($tableName)) {
            throw new RuntimeException(
                'A tabela de histórico não existe.'
            );
        }

        // Identifica os campos que ainda não existem.
        $missing = [
            'order_id' => !Schema::hasColumn($tableName, 'order_id'),
            'actor_user_id' => !Schema::hasColumn($tableName, 'actor_user_id'),
            'from_status' => !Schema::hasColumn($tableName, 'from_status'),
            'to_status' => !Schema::hasColumn($tableName, 'to_status'),
            'source' => !Schema::hasColumn($tableName, 'source'),
        ];

        // Não adiciona campos obrigatórios a uma tabela
        // que já contenha registros sem referência ao pedido.
        if (
            $missing['order_id'] &&
            DB::table($tableName)->exists()
        ) {
            throw new RuntimeException(
                'A tabela possui registros. Corrija os dados antes de prosseguir.'
            );
        }

        Schema::table($tableName, function (Blueprint $table) use ($missing) {

            if ($missing['order_id']) {
                $table->foreignId('order_id')
                    ->constrained('orders')
                    ->restrictOnDelete();

                $table->index(['order_id', 'id']);
            }

            if ($missing['actor_user_id']) {
                $table->foreignId('actor_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if ($missing['from_status']) {
                $table->string('from_status', 32)->nullable();
            }

            if ($missing['to_status']) {
                $table->string('to_status', 32);
            }

            if ($missing['source']) {
                $table->string('source', 50);
            }
        });
    }

    public function down(): void
    {
        // Correção conservadora:
        // não removemos colunas que possam conter
        // registros de auditoria.
    }
};
