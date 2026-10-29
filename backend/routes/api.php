<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;

Route::get('/health', function () {
    return response()->json([
        'app' => 'PetGo',
        'status' => 'ok',
    ]);
});

Route::apiResource('products', ProductController::class)
    ->only(['index']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json([
        'user' => $request->user()->only([
            'id',
            'name',
            'email',
            'role',
        ]),
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
