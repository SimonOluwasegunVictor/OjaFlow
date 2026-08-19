<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canViewCustomers($request)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $customers = Customer::query()
            ->withSum('sales as total_purchases', 'total')
            ->withSum('sales as outstanding_balance', 'balance_due')
            ->withMax('sales as last_purchase_at', 'created_at')
            ->where('business_id', $request->user()->business_id)
            ->when($payload['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => $this->customerData($customer));

        return response()->json([
            'customers' => $customers,
        ], JsonResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_CUSTOMERS->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate($this->rules($request));

        $customer = Customer::create([
            ...$payload,
            'business_id' => $request->user()->business_id,
        ]);

        return response()->json([
            'customer' => $this->customerData($customer),
        ], JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, string $customerId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_CUSTOMERS->value)) {
            return $this->permissionDenied();
        }

        $customer = $this->findCustomer($request, $customerId);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate($this->rules($request, $customer->id));
        $customer->update($payload);

        return response()->json([
            'customer' => $this->customerData($this->customerWithStats($request, $customer->id)),
        ], JsonResponse::HTTP_OK);
    }

    private function rules(Request $request, ?string $customerId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('customers', 'phone')
                    ->where('business_id', $request->user()->business_id)
                    ->ignore($customerId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function findCustomer(Request $request, string $customerId): ?Customer
    {
        return Customer::query()
            ->where('business_id', $request->user()->business_id)
            ->where('id', $customerId)
            ->first();
    }

    private function canViewCustomers(Request $request): bool
    {
        return $request->user()->hasPermission(StaffPermission::MANAGE_CUSTOMERS->value)
            || $request->user()->hasPermission(StaffPermission::RECORD_SALES->value)
            || $request->user()->hasPermission(StaffPermission::RECORD_DEBT_PAYMENTS->value);
    }

    private function customerData(Customer $customer): array
    {
        $totalPurchases = (float) ($customer->total_purchases ?? 0);
        $outstandingBalance = (float) ($customer->outstanding_balance ?? 0);

        return [
            'id' => $customer->id,
            'business_id' => $customer->business_id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'total_purchases' => number_format($totalPurchases, 2, '.', ''),
            'outstanding_balance' => number_format($outstandingBalance, 2, '.', ''),
            'last_purchase_at' => $customer->last_purchase_at,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }

    private function customerWithStats(Request $request, string $customerId): Customer
    {
        return Customer::query()
            ->withSum('sales as total_purchases', 'total')
            ->withSum('sales as outstanding_balance', 'balance_due')
            ->withMax('sales as last_purchase_at', 'created_at')
            ->where('business_id', $request->user()->business_id)
            ->where('id', $customerId)
            ->firstOrFail();
    }

    private function permissionDenied(): JsonResponse
    {
        return response()->json([
            'message' => 'You are not allowed to manage customers',
        ], JsonResponse::HTTP_FORBIDDEN);
    }
}
