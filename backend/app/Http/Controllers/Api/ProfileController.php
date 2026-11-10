<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validated();

        $user->update([
            'name' => trim($data['name']),
        ]);

        return response()->json([
            'user' => $user->only([
                'id',
                'name',
                'email',
                'role',
            ]),
        ]);
    }
}