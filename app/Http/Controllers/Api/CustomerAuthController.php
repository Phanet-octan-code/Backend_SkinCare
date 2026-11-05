<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    /**
     * Register a new online customer account.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6|confirmed',
            'address' => 'nullable|string|max:500',
        ], [
            'email.unique' => 'An account with this email address already exists. Please sign in.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        // Generate unique loyalty membership number
        $membershipNumber = 'MEM-BRZ-' . rand(1000, 9999);
        while (Customer::where('membership_number', $membershipNumber)->exists()) {
            $membershipNumber = 'MEM-BRZ-' . rand(1000, 9999);
        }

        $customer = Customer::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone'] ?? null),
            'password' => Hash::make($validated['password']),
            'address' => trim($validated['address'] ?? null),
            'membership_number' => $membershipNumber,
            'membership_level' => 'Bronze',
            'status' => 'active',
        ]);

        // Generate Sanctum authentication token
        $token = $customer->createToken('customer_storefront_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Your account has been created successfully. Welcome to SkinCare Boutique!',
            'token' => $token,
            'customer' => new CustomerResource($customer),
        ], 201);
    }

    /**
     * Authenticate customer with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', strtolower(trim($validated['email'])))->first();

        if (!$customer || !$customer->password || !Hash::check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email credentials or password provided.'],
            ]);
        }

        if ($customer->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact store support.',
            ], 403);
        }

        // Issue token
        $token = $customer->createToken('customer_storefront_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Signed in successfully.',
            'token' => $token,
            'customer' => new CustomerResource($customer),
        ]);
    }

    /**
     * Request a password recovery token.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($validated['email']));
        $customer = Customer::where('email', $email)->first();

        if (!$customer) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with this email address, you will receive password reset instructions.',
            ]);
        }

        // Generate secure 48-character token
        $token = bin2hex(random_bytes(24));
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $customer->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset instructions have been generated. Use the provided recovery token to reset your password.',
            'reset_token' => $token,
            'email' => $customer->email,
        ]);
    }

    /**
     * Reset password using the recovery token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $email = strtolower(trim($validated['email']));
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record || !Hash::check($validated['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['The recovery token is invalid or has expired. Please request a new recovery link.'],
            ]);
        }

        // Verify token age (valid for 60 minutes)
        if ($record->created_at && Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'token' => ['The recovery token has expired. Please request a new recovery link.'],
            ]);
        }

        $customer = Customer::where('email', $email)->first();
        if (!$customer) {
            throw ValidationException::withMessages([
                'email' => ['Customer account not found.'],
            ]);
        }

        $customer->password = Hash::make($validated['password']);
        $customer->save();

        // Invalidate used reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Invalidate old tokens and issue fresh session token
        $customer->tokens()->delete();
        $newToken = $customer->createToken('customer_storefront_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Your password has been successfully reset. You are now logged in.',
            'token' => $newToken,
            'customer' => new CustomerResource($customer),
        ]);
    }

    /**
     * Revoke active token on sign out.
     */
    public function logout(Request $request): JsonResponse
    {
        $customer = $request->user();
        if ($customer && $customer->currentAccessToken()) {
            $customer->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get authenticated customer profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'success' => true,
            'customer' => new CustomerResource($customer),
        ]);
    }

    /**
     * Update customer profile and password.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => ['required', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
            'address' => 'nullable|string|max:500',
            'current_password' => 'nullable|string|required_with:new_password',
            'new_password' => 'nullable|string|min:6|confirmed',
        ]);

        if (!empty($validated['new_password'])) {
            if (!$customer->password || !Hash::check($validated['current_password'], $customer->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password entered is incorrect.'],
                ]);
            }
            $customer->password = Hash::make($validated['new_password']);
        }

        $customer->name = trim($validated['name']);
        $customer->email = strtolower(trim($validated['email']));
        $customer->phone = trim($validated['phone'] ?? null);
        $customer->address = trim($validated['address'] ?? null);
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Your profile has been updated.',
            'customer' => new CustomerResource($customer),
        ]);
    }

    /**
     * Update customer password with current password verification.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if (!$customer->password || !Hash::check($validated['current_password'], $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password entered is incorrect.'],
            ]);
        }

        $customer->password = Hash::make($validated['password']);
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been updated successfully.',
        ]);
    }
}
