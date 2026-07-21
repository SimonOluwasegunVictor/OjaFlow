<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function permissions(): JsonResponse
    {
        return response()->json([
            'permissions' => StaffPermission::values(),
        ], JsonResponse::HTTP_OK);
    }

    public function index(Request $request): JsonResponse
    {
        $staff = User::query()
            ->where('business_id', $request->user()->business_id)
            ->where('role', UserRole::STAFF)
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->userData($user));

        return response()->json([
            'staff' => $staff,
        ], JsonResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (Gate::denies('manageStaff', User::class)) {
            return response()->json([
                'message' => 'Only a business admin can create staff',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash:ascii', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255', 'unique:users,phone'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:1000'],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(StaffPermission::values())],
        ]);

        $plainPassword = $payload['password'] ?? ('OjaFlow-' . Str::random(8) . '7!');

        $staff = User::create([
            'business_id' => $admin->business_id,
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'username' => Str::lower($payload['username']),
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'gender' => $payload['gender'],
            'address' => $payload['address'] ?? null,
            'role' => UserRole::STAFF,
            'status' => UserStatus::ACTIVE,
            'permissions' => $this->normalizePermissions($payload['permissions'] ?? []),
            'password' => Hash::make($plainPassword),
        ]);

        return response()->json([
            'user' => $this->userData($staff),
            'temporary_password' => $plainPassword,
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, string $staffId): JsonResponse
    {
        $staff = $this->findStaff($staffId, $request->user()->business_id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            'user' => $this->userData($staff),
        ], JsonResponse::HTTP_OK);
    }

    public function update(Request $request, string $staffId): JsonResponse
    {
        $staff = $this->findStaff($staffId, $request->user()->business_id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if (Gate::denies('update', $staff)) {
            return response()->json([
                'message' => 'You are not authorized to update this staff',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'alpha_dash:ascii', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($staff->id)],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($staff->id)],
            'gender' => ['sometimes', 'string', 'in:male,female,other'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(StaffPermission::values())],
        ]);

        if (!empty($payload['username'])) {
            $payload['username'] = Str::lower($payload['username']);
        }

        if (array_key_exists('permissions', $payload)) {
            $payload['permissions'] = $this->normalizePermissions($payload['permissions']);
        }

        $staff->update($payload);

        return response()->json([
            'user' => $this->userData($staff->fresh()),
        ], JsonResponse::HTTP_OK);
    }

    public function updateStatus(Request $request, string $staffId): JsonResponse
    {
        $staff = $this->findStaff($staffId, $request->user()->business_id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $staff->update([
            'status' => $payload['status'],
        ]);

        return response()->json([
            'user' => $this->userData($staff->fresh()),
        ], JsonResponse::HTTP_OK);
    }

    public function resetPassword(Request $request, string $staffId): JsonResponse
    {
        $staff = $this->findStaff($staffId, $request->user()->business_id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $plainPassword = $payload['password'] ?? ('OjaFlow-' . Str::random(8) . '7!');

        $staff->update([
            'password' => Hash::make($plainPassword),
        ]);

        return response()->json([
            'user' => $this->userData($staff->fresh()),
            'temporary_password' => $plainPassword,
        ], JsonResponse::HTTP_OK);
    }

    public function updatePermissions(Request $request, string $staffId): JsonResponse
    {
        $staff = $this->findStaff($staffId, $request->user()->business_id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(StaffPermission::values())],
        ]);

        $staff->update([
            'permissions' => $this->normalizePermissions($payload['permissions']),
        ]);

        return response()->json([
            'user' => $this->userData($staff->fresh()),
        ], JsonResponse::HTTP_OK);
    }

    public function destroy(Request $request, string $staffId): JsonResponse
    {
        $staff = $this->findStaff($staffId, $request->user()->business_id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if (Gate::denies('delete', $staff)) {
            return response()->json([
                'message' => 'You are not authorized to delete this staff',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully',
        ], JsonResponse::HTTP_OK);
    }

    private function findStaff(string $staffId, string $businessId): ?User
    {
        return User::query()
            ->where('id', $staffId)
            ->where('business_id', $businessId)
            ->where('role', UserRole::STAFF)
            ->first();
    }

    private function normalizePermissions(array $permissions): array
    {
        return array_values(array_unique($permissions));
    }
}
