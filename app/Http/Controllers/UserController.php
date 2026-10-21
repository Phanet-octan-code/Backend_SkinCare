<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of system users (Admin only).
     */
    public function index(Request $request): View
    {
        $query = User::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $users = $query->paginate(15)->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    /**
     * Store a newly created system user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(['admin', 'manager', 'cashier', 'inventory', 'hr'])],
            'password' => ['required', 'string', Password::defaults()->min(8)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('users.index')->with('status', 'New user account created successfully.');
    }

    /**
     * Update the specified user account.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => ['required', 'string', Rule::in(['admin', 'manager', 'cashier', 'inventory', 'hr'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', Password::defaults()->min(8)],
        ]);

        // Guard: Prevent admin from deactivating themselves or removing their admin role
        if ($user->id === Auth::id()) {
            if ($validated['status'] !== 'active') {
                return redirect()->route('users.index')->withErrors([
                    'error' => 'Security Error: You cannot deactivate your own logged-in administrator account.',
                ]);
            }
            if ($validated['role'] !== 'admin') {
                return redirect()->route('users.index')->withErrors([
                    'error' => 'Security Error: You cannot demote your own administrator role.',
                ]);
            }
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->status = $validated['status'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with('status', "User [{$user->name}] updated successfully.");
    }

    /**
     * Toggle user status (Activate / Deactivate).
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')->withErrors([
                'error' => 'Security Error: You cannot deactivate your own logged-in administrator account.',
            ]);
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->status = $newStatus;
        $user->save();

        $action = $newStatus === 'active' ? 'activated' : 'deactivated';

        return redirect()->route('users.index')->with('status', "Account [{$user->name}] has been {$action}.");
    }
}
