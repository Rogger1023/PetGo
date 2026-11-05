<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_histories';

    // Não utilizamos updated_at nos eventos.
    public $timestamps = false;

    // Evita atribuição em massa indiscriminada.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // Cada histórico pertence a um pedido.
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Usuário responsável pela alteração.
    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }
}
