<?php

namespace App\Http\Controllers;

use App\Models\User;

abstract class Controller
{
    protected function userData(User $user): array
    {
        $user->loadMissing('business');

        return [
            'id' => $user->id,
            'business_id' => $user->business_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'address' => $user->address,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'permissions' => $user->permissions ?? [],
            'business' => $user->business ? [
                'id' => $user->business->id,
                'name' => $user->business->name,
                'email' => $user->business->email,
                'phone' => $user->business->phone,
                'address' => $user->business->address,
                'logo' => $user->business->logo,
            ] : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
