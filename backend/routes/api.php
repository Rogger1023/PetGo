<?php

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

// Categorias públicas
Route::apiResource('categories', CategoryController::class)
    ->only(['index', 'show']);

// Autenticação
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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

        Route::apiResource('categories', CategoryController::class)
            ->only(['store', 'update', 'destroy']);

    });
