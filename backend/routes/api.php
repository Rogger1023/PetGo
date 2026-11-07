<?php

use App\Http\Controllers\Api\AdminStockMovementController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\OrderHistoryController;
use App\Http\Controllers\Api\AdminOrderHistoryController;
use App\Http\Controllers\Api\OrderFulfillmentController;
use App\Http\Controllers\Api\PaymentConfirmationController;
use App\Http\Controllers\Api\OrderCancellationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\GuestCartController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'app' => 'PetGo',
        'status' => 'ok',
    ]);
});

// Catálogo público: somente produtos ativos
Route::apiResource('products', ProductController::class)
    ->only(['index', 'show']);

Route::get('/products/{product}/image',[ProductController::class, 'image']);

// Categorias públicas
Route::apiResource('categories', CategoryController::class)
    ->only(['index', 'show']);

// Autenticação
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Carrinho de visitantes
Route::post('/guest/cart', [GuestCartController::class, 'store'])
    ->middleware('throttle:10,1');

Route::get('/guest/cart', [GuestCartController::class, 'show'])
    ->middleware('throttle:60,1');
    Route::post('/guest/cart/items', [GuestCartController::class, 'addItem'])
    ->middleware('throttle:60,1');

Route::patch('/guest/cart/items/{item}', [GuestCartController::class, 'updateItem'])
    ->whereNumber('item')
    ->middleware('throttle:60,1');

Route::delete('/guest/cart/items/{item}', [GuestCartController::class, 'removeItem'])
    ->whereNumber('item')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Rotas de usuários autenticados
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user()->only([
                'id',
                'name',
                'email',
                'role',
            ]),
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    // Carrinho do usuário autenticado
    Route::get('/cart', [CartController::class, 'index']);

    Route::post('/cart/items', [CartController::class, 'store']);

    Route::patch('/cart/items/{item}', [CartController::class, 'update'])
        ->whereNumber('item');

    Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])
        ->whereNumber('item');
    
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1');

        // Histórico de pedidos do usuário
    Route::get('/orders', [OrderController::class, 'index']);

    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->whereNumber('order');
        // Cancelamento de pedidos do cliente
    Route::post(
        '/orders/{order}/cancel',
        OrderCancellationController::class
    )->whereNumber('order');

    // Histórico de pedidos do cliente autenticado.
    Route::get(
        '/orders/{order}/history',
        OrderHistoryController::class
    )
        ->whereNumber('order')
        ->name('orders.history');
        
    // Endereços do cliente autenticado
    Route::get('/addresses', [AddressController::class, 'index'])
        ->name('addresses.index');

    Route::post('/addresses', [AddressController::class, 'store'])
        ->name('addresses.store');

    Route::get('/addresses/{address}', [AddressController::class, 'show'])
        ->whereNumber('address')
        ->name('addresses.show');

    Route::put('/addresses/{address}', [AddressController::class, 'update'])
        ->whereNumber('address')
        ->name('addresses.update');

    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])
        ->whereNumber('address')
        ->name('addresses.destroy');
});

/*
|--------------------------------------------------------------------------
| Rotas administrativas
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:sanctum', 'admin']) //Sanctum para autenticação e middleware admin para verificar se o usuário é administrador
    ->group(function () {

        Route::apiResource('products', ProductController::class)
            ->only(['store', 'update', 'destroy']);
        
        Route::get(
            '/products/{product}/stock-movements',
            AdminStockMovementController::class
        )
            ->whereNumber('product')
            ->name('products.stock-movements.index');

        Route::apiResource('categories', CategoryController::class)
            ->only(['store', 'update', 'destroy']);

        // Consulta administrativa de pedidos
        Route::get('/orders', [AdminOrderController::class, 'index'])
            ->name('orders.index');

        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])
            ->whereNumber('order')
            ->name('orders.show');
        // Confirmação simulada de pagamento
        Route::post(
            '/orders/{order}/confirm-payment',
            PaymentConfirmationController::class
        )
            ->whereNumber('order')
            ->name('orders.confirm-payment');
        // Atualização do andamento logístico do pedido
        Route::patch(
            '/orders/{order}/status',
            OrderFulfillmentController::class
        )
            ->whereNumber('order')
            ->name('orders.update-status');
        // Histórico administrativo dos pedidos.
        Route::get(
            '/orders/{order}/history',
            AdminOrderHistoryController::class
        )
            ->whereNumber('order')
            ->name('orders.history');

    });
