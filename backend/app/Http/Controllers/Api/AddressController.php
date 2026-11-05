<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService
    ) {
    }

    // GET /api/addresses
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    // POST /api/addresses
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        $address = $this->addressService->create(
            $request->user(),
            $data
        );

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    // GET /api/addresses/{address}
    public function show(
        Request $request,
        int $address
    ): AddressResource {
        $addressModel = $request->user()
            ->addresses()
            ->whereKey($address)
            ->firstOrFail();

        return new AddressResource($addressModel);
    }

    // PUT /api/addresses/{address}
    public function update(
        UpdateAddressRequest $request,
        int $address
    ): AddressResource {
        $data = $request->validated();

        $updatedAddress = $this->addressService->update(
            $request->user(),
            $address,
            $data
        );

        return new AddressResource($updatedAddress);
    }

    // DELETE /api/addresses/{address}
    public function destroy(
        Request $request,
        int $address
    ): Response {
        $this->addressService->delete(
            $request->user(),
            $address
        );

        return response()->noContent();
    }
}
