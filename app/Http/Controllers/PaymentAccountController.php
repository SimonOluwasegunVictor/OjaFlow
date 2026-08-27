<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use App\Models\PaymentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'accounts' => PaymentAccount::query()
                ->where('business_id', $request->user()->business_id)
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn (PaymentAccount $account) => $this->accountData($account)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_SETTINGS->value)) {
            return $this->permissionDenied();
        }

        $account = PaymentAccount::create([
            ...$request->validate($this->rules()),
            'business_id' => $request->user()->business_id,
        ]);

        return response()->json(['account' => $this->accountData($account)], JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, string $accountId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_SETTINGS->value)) {
            return $this->permissionDenied();
        }

        $account = PaymentAccount::query()
            ->where('business_id', $request->user()->business_id)
            ->where('id', $accountId)
            ->first();

        if (!$account) {
            return $this->response('Payment account not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $account->update($request->validate($this->rules()));

        return response()->json(['account' => $this->accountData($account->fresh())]);
    }

    public function destroy(Request $request, string $accountId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_SETTINGS->value)) {
            return $this->permissionDenied();
        }

        $account = PaymentAccount::query()
            ->where('business_id', $request->user()->business_id)
            ->where('id', $accountId)
            ->first();

        if (!$account) {
            return $this->response('Payment account not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $account->update(['is_active' => false]);

        return response()->json(['message' => 'Payment account disabled']);
    }

    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['bank', 'pos'])],
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['required_if:type,bank', 'nullable', 'string', 'max:50'],
            'terminal_id' => ['required_if:type,pos', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function accountData(PaymentAccount $account): array
    {
        return [
            'id' => $account->id,
            'type' => $account->type,
            'name' => $account->name,
            'provider' => $account->provider,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'terminal_id' => $account->terminal_id,
            'is_active' => $account->is_active,
        ];
    }

    private function permissionDenied(): JsonResponse
    {
        return $this->response('Only an admin can manage payment accounts', JsonResponse::HTTP_FORBIDDEN);
    }
}
