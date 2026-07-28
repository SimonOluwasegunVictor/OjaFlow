<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $payload = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:255', 'unique:users'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:1000'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['nullable', 'string', 'email', 'max:255', 'unique:businesses,email'],
            'business_phone' => ['nullable', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        [$user, $token] = DB::transaction(function () use ($payload) {
            $user = User::create([
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'gender' => $payload['gender'],
                'address' => $payload['address'] ?? null,
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
                'password' => Hash::make($payload['password']),
            ]);

            $business = Business::create([
                'owner_id' => $user->id,
                'name' => $payload['business_name'],
                'email' => $payload['business_email'] ?? null,
                'phone' => $payload['business_phone'] ?? $payload['phone'] ?? null,
                'address' => $payload['business_address'] ?? null,
            ]);

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'phone' => $business->phone,
                'address' => $business->address,
                'is_main' => true,
            ]);

            $user->update([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
            ]);

            $token = $user->createToken('admin-token', [UserRole::ADMIN->value])->plainTextToken;

            return [$user->load(['business', 'branch']), $token];
        });

        return response()->json(
            $this->userPayload($user, $token),
            JsonResponse::HTTP_CREATED
        );
    }

    public function login(Request $request)
    {
        $request->merge([
            'login' => $request->input('login', $request->input('email')),
        ]);

        $payload = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $payload['login'])
            ->orWhere('phone', $payload['login'])
            ->orWhere('username', $payload['login'])
            ->first();

        if (!$user || !Hash::check($payload['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            return response()->json(['message' => 'Your account is inactive'], JsonResponse::HTTP_FORBIDDEN);
        }

        $token = $user->createToken($user->role->value . '-token', [$user->role->value])->plainTextToken;

        return response()->json(
            $this->userPayload($user, $token),
            JsonResponse::HTTP_OK
        );
    }

    public function update(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $authenticatedUser = $request->user();
        $isSelf = $authenticatedUser->id === $user->id;

        if (Gate::denies('update', $user)) {
            return response()->json([
                'message' => 'You are not authorized to update this user',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($user->id)],
            'gender' => ['sometimes', 'string', 'in:male,female,other'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'current_password' => ['sometimes', 'string'],
            'new_password' => ['sometimes', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (!$authenticatedUser->isAdmin()) {
            unset($payload['status']);
        }

        if (!empty($payload['new_password'])) {
            if ($isSelf && empty($payload['current_password'])) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is required'],
                ]);
            }

            if ($isSelf && !Hash::check($payload['current_password'] ?? '', $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect'],
                ]);
            }

            $payload['password'] = Hash::make($payload['new_password']);
        } else {
            unset($payload['new_password']);
        }

        unset($payload['current_password']);

        $user->update($payload);

        return response()->json(
            ['user' => $this->userData($user->fresh())],
            JsonResponse::HTTP_OK
        );
    }

    public function delete(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Business admins must be removed through a business closure or ownership transfer flow',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (Gate::denies('delete', $user)) {
            return response()->json([
                'message' => 'You are not authorized to delete this user',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], JsonResponse::HTTP_OK);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Successfully logged out'], JsonResponse::HTTP_OK);
    }

    private function userPayload(User $user, string $token): array
    {
        return [
            'user' => $this->userData($user),
            'token' => $token,
        ];
    }
}
