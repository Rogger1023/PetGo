<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressService
{
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {

            // Bloqueia o usuário para serializar alterações
            // de endereços da mesma conta.
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // O primeiro endereço será sempre o principal.
            $isFirstAddress = !$user->addresses()->exists();

            $makeDefault = $isFirstAddress
                || (bool) ($data['is_default'] ?? false);

            // A definição do endereço principal é controlada
            // explicitamente pelo serviço.
            unset($data['is_default']);

            if ($makeDefault) {
                $user->addresses()
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // Cria o endereço associado ao usuário autenticado.
            $address = $user->addresses()->create($data);

            $address->is_default = $makeDefault;
            $address->save();

            return $address->fresh();

        }, 3);
    }

    public function update(
        User $user,
        int $addressId,
        array $data
    ): Address {
        return DB::transaction(function () use (
            $user,
            $addressId,
            $data
        ) {

            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Só encontra endereços pertencentes ao usuário.
            $address = $user->addresses()
                ->whereKey($addressId)
                ->lockForUpdate()
                ->firstOrFail();

            $hasDefaultField = array_key_exists(
                'is_default',
                $data
            );

            $makeDefault = (bool) ($data['is_default'] ?? false);

            // Não permite desmarcar diretamente o principal.
            // O cliente deve escolher outro endereço principal.
            if (
                $hasDefaultField &&
                !$makeDefault &&
                $address->is_default
            ) {
                throw ValidationException::withMessages([
                    'is_default' =>
                        'Defina outro endereço como principal antes de desmarcar este.',
                ]);
            }

            unset($data['is_default']);

            // Atualiza somente os campos permitidos pelo Model.
            $address->fill($data);

            if ($makeDefault) {
                $user->addresses()
                    ->where('is_default', true)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);

                $address->is_default = true;
            }

            $address->save();

            return $address->fresh();

        }, 3);
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId) {

            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $address = $user->addresses()
                ->whereKey($addressId)
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = $address->is_default;

            $address->delete();

            // Se excluiu o principal, escolhe outro endereço.
            if ($wasDefault) {
                $nextAddress = $user->addresses()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($nextAddress !== null) {
                    $nextAddress->is_default = true;
                    $nextAddress->save();
                }
            }

        }, 3);
    }
}
