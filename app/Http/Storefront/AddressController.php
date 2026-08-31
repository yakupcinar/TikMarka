<?php

namespace App\Http\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Storefront\Requests\AddressRequest;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /** Müşterinin kendi adresleri, yenisi üstte. */
    public function index(Request $istek): JsonResponse
    {
        return response()->json([
            'addresses' => $this->musteri($istek)->addresses()->latest('id')->get(),
        ]);
    }

    public function store(AddressRequest $istek): JsonResponse
    {
        $adres = $this->musteri($istek)->addresses()->create($istek->validated());

        return response()->json(['address' => $adres], 201);
    }

    public function update(AddressRequest $istek, string $adres): JsonResponse
    {
        $kayit = $this->sahipOl($istek, $adres);
        $kayit->update($istek->validated());

        return response()->json(['address' => $kayit]);
    }

    public function destroy(Request $istek, string $adres): JsonResponse
    {
        $this->sahipOl($istek, $adres)->delete();

        return response()->json(['message' => 'Adres silindi.']);
    }

    private function musteri(Request $istek): Customer
    {
        $kullanici = $istek->user();

        abort_unless($kullanici instanceof Customer, 401);

        return $kullanici;
    }
    private function sahipOl(Request $istek, string $uuid): Address
    {
        return $this->musteri($istek)->addresses()->where('uuid', $uuid)->firstOrFail();
    }
}
