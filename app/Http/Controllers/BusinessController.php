<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['business' => $this->businessData($request->user()->business)]);
    }

    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_SETTINGS->value)) {
            return $this->response('Only an admin can update business settings', JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('businesses', 'email')->ignore($request->user()->business_id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt_size' => ['sometimes', Rule::in(['58mm', '80mm', 'a4'])],
            'settings' => ['sometimes', 'array'],
            'settings.sms_enabled' => ['sometimes', 'boolean'],
            'settings.whatsapp_enabled' => ['sometimes', 'boolean'],
            'settings.low_stock_alerts' => ['sometimes', 'boolean'],
        ]);

        /** @var Business $business */
        $business = $request->user()->business;

        if (array_key_exists('settings', $payload)) {
            $payload['settings'] = array_merge($business->settings ?? [], $payload['settings']);
        }

        $business->update($payload);

        return response()->json(['business' => $this->businessData($business->fresh())]);
    }

    private function businessData(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'email' => $business->email,
            'phone' => $business->phone,
            'address' => $business->address,
            'logo' => $business->logo,
            'receipt_footer' => $business->receipt_footer,
            'receipt_size' => $business->receipt_size,
            'settings' => $business->settings ?? [],
        ];
    }
}
