<?php

namespace App\Http\Controllers;

use App\Enums\BranchStatus;
use App\Enums\SalePaymentMethod;
use App\Enums\StaffPermission;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\DebtPayment;
use App\Models\Sale;
use App\Models\SalePayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function endOfDay(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::VIEW_REPORTS->value)
            && !$request->user()->hasPermission(StaffPermission::VIEW_SALES->value)) {
            return $this->response('You are not allowed to view end-of-day reports', JsonResponse::HTTP_FORBIDDEN);
        }

        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? now()->toDateString();
        $branch = $this->targetBranch($request);
        $sales = Sale::query()->where('business_id', $request->user()->business_id)->where('branch_id', $branch->id)->whereDate('created_at', $date);
        $salePayments = SalePayment::query()->with('account')->where('business_id', $request->user()->business_id)->where('branch_id', $branch->id)->whereDate('created_at', $date)->get();
        $debtPayments = DebtPayment::query()->where('business_id', $request->user()->business_id)->where('branch_id', $branch->id)->whereDate('created_at', $date);

        $paymentBreakdown = $salePayments->groupBy(fn (SalePayment $payment) => $payment->method->value)->map(fn ($payments, $method) => [
            'method' => $method,
            'label' => ucfirst($method),
            'amount' => number_format((float) $payments->sum('amount'), 2, '.', ''),
            'payments' => $payments->map(fn (SalePayment $payment) => [
                'amount' => $payment->amount,
                'account' => $payment->account?->name,
                'destination' => $payment->account?->account_number ?? $payment->account?->terminal_id,
            ])->values(),
        ])->values();

        $debtReceived = (float) (clone $debtPayments)->sum('amount');

        return response()->json([
            'date' => $date,
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'summary' => [
                'transactions' => (clone $sales)->count(),
                'sales_total' => number_format((float) (clone $sales)->sum('total'), 2, '.', ''),
                'sale_payments' => number_format((float) $salePayments->sum('amount'), 2, '.', ''),
                'debt_payments' => number_format($debtReceived, 2, '.', ''),
                'money_received' => number_format((float) $salePayments->sum('amount') + $debtReceived, 2, '.', ''),
                'outstanding' => number_format((float) (clone $sales)->sum('balance_due'), 2, '.', ''),
            ],
            'payment_breakdown' => $paymentBreakdown,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::VIEW_DASHBOARD->value)
            && !$request->user()->hasPermission(StaffPermission::VIEW_REPORTS->value)) {
            return $this->response('You are not allowed to view dashboard reports', JsonResponse::HTTP_FORBIDDEN);
        }

        $branch = $this->targetBranch($request);
        $today = Carbon::today();
        $salesToday = Sale::query()
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->whereDate('created_at', $today);

        $weekStart = $today->copy()->subDays(6)->startOfDay();
        $weekSales = Sale::query()
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->whereBetween('created_at', [$weekStart, $today->copy()->endOfDay()]);

        $receivedToday = (clone $salesToday)->sum('amount_paid') + DebtPayment::query()
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->whereDate('created_at', $today)
            ->sum('amount');

        $profitToday = (clone $salesToday)
            ->with('items')
            ->get()
            ->sum(fn (Sale $sale) => $sale->items->sum(fn ($item) => ((float) $item->unit_price - (float) $item->cost_price) * $item->quantity));

        $debts = Sale::query()
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->where('balance_due', '>', 0);

        $lowStock = BranchProductStock::query()
            ->with('product')
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->orderBy('quantity')
            ->limit(5)
            ->get()
            ->map(fn (BranchProductStock $stock) => [
                'id' => $stock->product_id,
                'name' => $stock->product->name,
                'quantity' => $stock->quantity,
                'reorder_level' => $stock->reorder_level,
                'unit' => $stock->product->unit,
            ]);

        $weekTotals = $weekSales->get()->groupBy(fn (Sale $sale) => $sale->created_at->toDateString());
        $salesChart = collect(range(6, 0))->map(function (int $daysAgo) use ($today, $weekTotals) {
            $date = $today->copy()->subDays($daysAgo);

            return [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'value' => (float) ($weekTotals->get($date->toDateString())?->sum('total') ?? 0),
            ];
        });

        $paymentBreakdown = collect(SalePaymentMethod::cases())->map(function (SalePaymentMethod $method) use ($salesToday) {
            return [
                'label' => ucfirst($method->value),
                'method' => $method->value,
                'value' => (float) (clone $salesToday)->where('payment_method', $method->value)->sum('total'),
            ];
        })->filter(fn (array $item) => $item['value'] > 0)->values();

        $recentSales = (clone $salesToday)->with('customer')->latest()->limit(5)->get()->map(fn (Sale $sale) => [
            'order_number' => $sale->order_number,
            'customer_name' => $sale->customer?->name ?? 'Walk-in Customer',
            'total' => $sale->total,
            'payment_status' => $sale->payment_status->value,
            'created_at' => $sale->created_at,
        ]);

        return response()->json([
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'metrics' => [
                'sales_today' => number_format((float) (clone $salesToday)->sum('total'), 2, '.', ''),
                'money_received' => number_format((float) $receivedToday, 2, '.', ''),
                'customers_owing' => number_format((float) $debts->sum('balance_due'), 2, '.', ''),
                'estimated_profit' => number_format((float) $profitToday, 2, '.', ''),
                'transactions_today' => (clone $salesToday)->count(),
                'low_stock_products' => $lowStock->count(),
            ],
            'sales_chart' => $salesChart,
            'payment_breakdown' => $paymentBreakdown,
            'recent_sales' => $recentSales,
            'low_stock' => $lowStock->values(),
        ]);
    }

    private function targetBranch(Request $request): Branch
    {
        $query = Branch::query()
            ->where('business_id', $request->user()->business_id)
            ->where('status', BranchStatus::ACTIVE);

        if (!$request->user()->isAdmin()) {
            return $query->where('id', $request->user()->branch_id)->firstOrFail();
        }

        return $query->where('id', $request->user()->branch_id)->first()
            ?? Branch::query()
                ->where('business_id', $request->user()->business_id)
                ->where('status', BranchStatus::ACTIVE)
                ->where('is_main', true)
                ->firstOrFail();
    }
}
