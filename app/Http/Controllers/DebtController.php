<?php

namespace App\Http\Controllers;

use App\Enums\SalePaymentMethod;
use App\Enums\SalePaymentStatus;
use App\Enums\StaffPermission;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DebtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_DEBT_PAYMENTS->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'status' => ['nullable', Rule::in(['outstanding', 'partial', 'overdue', 'paid'])],
        ]);

        $status = $payload['status'] ?? 'outstanding';
        $baseQuery = Sale::query()
            ->with(['customer', 'debtPayments'])
            ->where('business_id', $request->user()->business_id)
            ->whereNotNull('customer_id');

        $debts = (clone $baseQuery)
            ->when($status === 'outstanding', fn ($query) => $query->where('balance_due', '>', 0)->where('amount_paid', '<=', 0)->where(fn ($query) => $query->whereNull('due_date')->orWhereDate('due_date', '>=', now())))
            ->when($status === 'partial', fn ($query) => $query->where('balance_due', '>', 0)->where('amount_paid', '>', 0)->where(fn ($query) => $query->whereNull('due_date')->orWhereDate('due_date', '>=', now())))
            ->when($status === 'overdue', fn ($query) => $query->where('balance_due', '>', 0)->whereDate('due_date', '<', now()))
            ->when($status === 'paid', fn ($query) => $query->where('balance_due', '<=', 0))
            ->latest()
            ->get()
            ->map(fn (Sale $sale) => $this->debtData($sale));

        $totalOwed = (clone $baseQuery)->where('balance_due', '>', 0)->sum('balance_due');
        $overdue = (clone $baseQuery)->where('balance_due', '>', 0)->whereDate('due_date', '<', now())->sum('balance_due');
        $customers = (clone $baseQuery)->where('balance_due', '>', 0)->distinct('customer_id')->count('customer_id');

        return response()->json([
            'summary' => [
                'total_owed' => number_format((float) $totalOwed, 2, '.', ''),
                'overdue' => number_format((float) $overdue, 2, '.', ''),
                'customers' => $customers,
            ],
            'debts' => $debts,
        ], JsonResponse::HTTP_OK);
    }

    public function recordPayment(Request $request, string $saleId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_DEBT_PAYMENTS->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in([
                SalePaymentMethod::CASH->value,
                SalePaymentMethod::TRANSFER->value,
                SalePaymentMethod::POS->value,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $sale = Sale::query()
            ->where('business_id', $request->user()->business_id)
            ->where('id', $saleId)
            ->where('balance_due', '>', 0)
            ->first();

        if (!$sale) {
            return response()->json([
                'message' => 'Debt not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $sale = DB::transaction(function () use ($request, $sale, $payload) {
            $amount = min((float) $payload['amount'], (float) $sale->balance_due);
            $balance = max(0, (float) $sale->balance_due - $amount);
            $paid = (float) $sale->amount_paid + $amount;

            $sale->debtPayments()->create([
                'business_id' => $sale->business_id,
                'branch_id' => $sale->branch_id,
                'customer_id' => $sale->customer_id,
                'user_id' => $request->user()->id,
                'amount' => $amount,
                'payment_method' => $payload['payment_method'],
                'note' => $payload['note'] ?? null,
            ]);

            $sale->update([
                'amount_paid' => $paid,
                'balance_due' => $balance,
                'payment_status' => $balance <= 0 ? SalePaymentStatus::PAID : SalePaymentStatus::PARTIAL,
                'paid_at' => $balance <= 0 ? now() : null,
            ]);

            return $sale->fresh(['customer', 'debtPayments']);
        });

        return response()->json([
            'debt' => $this->debtData($sale),
        ], JsonResponse::HTTP_OK);
    }

    private function debtData(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'order_number' => $sale->order_number,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
            ] : null,
            'original_amount' => $sale->total,
            'paid_amount' => $sale->amount_paid,
            'balance_due' => $sale->balance_due,
            'payment_status' => $sale->payment_status->value,
            'due_date' => $sale->due_date?->toDateString(),
            'is_overdue' => (float) $sale->balance_due > 0 && $sale->due_date && $sale->due_date->isPast(),
            'created_at' => $sale->created_at,
        ];
    }

    private function permissionDenied(): JsonResponse
    {
        return response()->json([
            'message' => 'You are not allowed to record debt payments',
        ], JsonResponse::HTTP_FORBIDDEN);
    }
}
