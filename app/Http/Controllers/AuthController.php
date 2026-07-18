<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $payload = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:male,female'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $payload['password'] = Hash::make($payload['password']);
        $payload['role'] = 'admin';

        $user = User::create($payload);

        $token = $user->createToken('token')->plainTextToken;

        return response()->json(
            $this->userPayload($user, $token),
            JsonResponse::HTTP_CREATED
        );
    }

    public function login(Request $request)
    {
        $payload = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $payload['email'])->first();

        if (!$user || !Hash::check($payload['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken('token')->plainTextToken;

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
                'message' => 'User not found'
                ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($request->user()->id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to update this user'
                ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'string', 'in:male,female'],
            'address' => ['sometimes', 'string', 'max:255'],
            'current_password' => ['required_with:new_password', 'string'],
            'new_password' => ['sometimes', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (!empty($payload['new_password'])) {
            if (!Hash::check($payload['current_password'] ?? '', $user->password)) {
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
            $user,
            JsonResponse::HTTP_OK
        );
    }

    public function delete(Request $request, $userId)
    {
        $authenticatedUser = $request->user();

        if (!$authenticatedUser || $authenticatedUser->id !== $userId) {
            return response()->json([
                'message' => 'You are not authorized to delete this user'
                ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
            ], JsonResponse::HTTP_OK);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Successfully logged out'], JsonResponse::HTTP_OK);
    }

    private function userPayload(User $user, string $token): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'token' => $token,
        ];
    }
}
