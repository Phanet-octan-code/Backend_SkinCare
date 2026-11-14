<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    /**
     * List all saved delivery addresses for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => CustomerAddressResource::collection($addresses),
        ]);
    }

    /**
     * Store a new delivery address.
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'label' => 'nullable|string|max:50',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $address = DB::transaction(function () use ($customer, $validated) {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            // Fast EXISTS check instead of full COUNT(*)
            $hasExisting = $customer->addresses()->exists();
            if ($isDefault || !$hasExisting) {
                $isDefault = true;
                $customer->addresses()->update(['is_default' => false]);
            }

            return $customer->addresses()->create([
                'label' => $validated['label'] ?? 'Home',
                'recipient_name' => $validated['recipient_name'],
                'phone' => $validated['phone'],
                'address_line1' => $validated['address_line1'],
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'],
                'state_province' => $validated['state_province'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'country' => $validated['country'] ?? 'Cambodia',
                'is_default' => $isDefault,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'data' => new CustomerAddressResource($address),
        ], 201);
    }

    /**
     * Update an existing address with strict customer ownership check.
     */
    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $request->user();

        if ($address->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:50',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($customer, $address, $validated) {
            if (!empty($validated['is_default'])) {
                $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }
            $address->update($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => new CustomerAddressResource($address->fresh()),
        ]);
    }

    /**
     * Delete an address with ownership enforcement.
     */
    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $request->user();

        if ($address->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        DB::transaction(function () use ($customer, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            // If default was deleted, assign another address as default
            if ($wasDefault) {
                $next = $customer->addresses()->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }
}
