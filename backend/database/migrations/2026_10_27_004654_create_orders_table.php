
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 32)
                ->default('pending_payment');

            $table->unsignedBigInteger('shipping_cents');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('total_cents');

            $table->json('address_snapshot');

            $table->string('checkout_key', 64)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
