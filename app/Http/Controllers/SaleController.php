<?php

namespace App\Http\Controllers;

use App\Enums\SalePaymentMethod;
use App\Enums\SalePaymentStatus;
use App\Enums\StaffPermission;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentAccount;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::VIEW_SALES->value)
            && !$request->user()->hasPermission(StaffPermission::VIEW_REPORTS->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('business_id', $request->user()->business_id)],
            'search' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', Rule::enum(SalePaymentStatus::class)],
        ]);

        $branch = $this->targetBranch($request, $request->user()->isAdmin() ? ($payload['branch_id'] ?? null) : null);
        $sales = Sale::query()
            ->with(['customer', 'user', 'payments.account', 'items'])
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->when($payload['date'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', $date))
            ->when($payload['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($payload['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($payload['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($payload['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'sales' => $sales->map(fn (Sale $sale) => $this->saleData($sale)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'cart_id' => ['required', Rule::exists('carts', 'id')->where('business_id', $request->user()->business_id)],
            'payment_method' => ['required', Rule::enum(SalePaymentMethod::class)],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'due_date' => ['nullable', 'date'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in([
                SalePaymentMethod::CASH->value,
                SalePaymentMethod::TRANSFER->value,
                SalePaymentMethod::POS->value,
            ])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'payments.*.payment_account_id' => ['nullable', Rule::exists('payment_accounts', 'id')->where('business_id', $request->user()->business_id)],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $paymentLines = collect($payload['payments'] ?? []);

        if ($paymentLines->isEmpty() && (float) ($payload['amount_paid'] ?? 0) > 0 && $payload['payment_method'] !== SalePaymentMethod::CREDIT->value) {
            $paymentLines = collect([[
                'method' => $payload['payment_method'],
                'amount' => $payload['amount_paid'],
            ]]);
        }

        if ($payload['payment_method'] === SalePaymentMethod::SPLIT->value && $paymentLines->count() < 2) {
            return $this->response('Split payment must contain at least two payment methods', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($paymentLines as $line) {
            $method = SalePaymentMethod::from($line['method']);
            $account = !empty($line['payment_account_id'])
                ? PaymentAccount::query()->where('business_id', $request->user()->business_id)->where('id', $line['payment_account_id'])->first()
                : null;

            if (in_array($method, [SalePaymentMethod::TRANSFER, SalePaymentMethod::POS], true)
                && (!$account || $account->type !== ($method === SalePaymentMethod::POS ? 'pos' : 'bank') || !$account->is_active)) {
                return $this->response('Select an active matching account or POS terminal for each payment', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $amountPaid = (float) $paymentLines->sum(fn (array $line) => (float) $line['amount']);
        $paymentMethod = $paymentLines->count() > 1
            ? SalePaymentMethod::SPLIT
            : SalePaymentMethod::from($paymentLines->first()['method'] ?? $payload['payment_method']);
        $branch = $this->targetBranch($request);
        $cart = Cart::query()
            ->with(['items.product', 'customer'])
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->where('id', $payload['cart_id'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return $this->response('Cart is empty or no longer active', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($paymentMethod === SalePaymentMethod::CREDIT && !$cart->customer_id) {
            return $this->response('A customer must be selected for credit sales', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sale = DB::transaction(function () use ($request, $payload, $branch, $cart, $amountPaid, $paymentMethod, $paymentLines) {
            $cart = Cart::query()
                ->with(['items.product', 'customer'])
                ->where('id', $cart->id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = $cart->items->map(function (CartItem $item) use ($branch) {
                $stock = BranchProductStock::query()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $item->quantity) {
                    abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, "{$item->product_name} does not have enough stock");
                }

                return [
                    'stock' => $stock,
                    'business_id' => $item->business_id,
                    'branch_id' => $item->branch_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'cost_price' => $item->product->cost_price,
                    'line_total' => $item->line_total,
                ];
            });

            $subtotal = (float) $items->sum('line_total');
            $discount = min((float) $cart->discount, $subtotal);
            $total = max(0, $subtotal - $discount);
            $paid = min($amountPaid, $total);

            if ($amountPaid > $total) {
                abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Payment total cannot be greater than the sale total');
            }

            $balance = max(0, $total - $paid);
            $status = $this->paymentStatus($total, $paid);

            if ($balance > 0 && !$cart->customer_id) {
                abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'A customer must be selected when a balance remains');
            }

            if ($balance > 0 && empty($payload['due_date'])) {
                abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'A due date is required for unpaid balances');
            }

            $sale = Sale::create([
                'business_id' => $request->user()->business_id,
                'branch_id' => $branch->id,
                'customer_id' => $cart->customer_id,
                'user_id' => $request->user()->id,
                'cart_id' => $cart->id,
                'order_number' => $this->nextOrderNumber($request),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'amount_paid' => $paid,
                'balance_due' => $balance,
                'payment_method' => $paymentMethod,
                'payment_status' => $status,
                'due_date' => $balance > 0 ? $payload['due_date'] : null,
                'paid_at' => $balance <= 0 ? now() : null,
            ]);

            $paymentLines->each(function (array $line) use ($sale, $request, $branch) {
                $sale->payments()->create([
                    'business_id' => $request->user()->business_id,
                    'branch_id' => $branch->id,
                    'user_id' => $request->user()->id,
                    'payment_account_id' => $line['payment_account_id'] ?? null,
                    'method' => $line['method'],
                    'amount' => $line['amount'],
                    'reference' => $line['reference'] ?? null,
                    'note' => $line['note'] ?? null,
                ]);
            });

            $items->each(function (array $item) use ($sale, $request, $branch) {
                $sale->items()->create([
                    'business_id' => $item['business_id'],
                    'branch_id' => $item['branch_id'],
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'line_total' => $item['line_total'],
                ]);

                /** @var BranchProductStock $stock */
                $stock = $item['stock'];
                $previous = $stock->quantity;
                $newQuantity = $previous - $item['quantity'];

                $stock->update([
                    'quantity' => $newQuantity,
                ]);

                StockMovement::create([
                    'business_id' => $request->user()->business_id,
                    'branch_id' => $branch->id,
                    'product_id' => $item['product_id'],
                    'user_id' => $request->user()->id,
                    'type' => StockMovementType::SALE,
                    'quantity' => $item['quantity'],
                    'previous_quantity' => $previous,
                    'new_quantity' => $newQuantity,
                    'reason' => $sale->order_number,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                ]);
            });

            $cart->update([
                'status' => 'checked_out',
                'checked_out_at' => now(),
            ]);

            return $sale->fresh(['customer', 'user', 'items', 'payments.account']);
        });

        return response()->json([
            'sale' => $this->saleData($sale),
        ], JsonResponse::HTTP_CREATED);
    }

    private function paymentStatus(float $total, float $paid): SalePaymentStatus
    {
        if ($paid >= $total) {
            return SalePaymentStatus::PAID;
        }

        return $paid > 0 ? SalePaymentStatus::PARTIAL : SalePaymentStatus::OUTSTANDING;
    }

    private function targetBranch(Request $request, ?string $branchId = null): Branch
    {
        $query = Branch::query()
            ->where('business_id', $request->user()->business_id)
            ->where('status', 'active');

        if (!$request->user()->isAdmin()) {
            return $query->where('id', $request->user()->branch_id)->firstOrFail();
        }

        if ($branchId) {
            return $query->where('id', $branchId)->firstOrFail();
        }

        return $query->where('id', $request->user()->branch_id)->first()
            ?? Branch::query()->where('business_id', $request->user()->business_id)->where('status', 'active')->where('is_main', true)->firstOrFail();
    }

    private function nextOrderNumber(Request $request): string
    {
        $next = Sale::query()
            ->where('business_id', $request->user()->business_id)
            ->lockForUpdate()
            ->count() + 1;

        return 'ORD-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function saleData(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'order_number' => $sale->order_number,
            'cart_id' => $sale->cart_id,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
            ] : null,
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'total' => $sale->total,
            'amount_paid' => $sale->amount_paid,
            'balance_due' => $sale->balance_due,
            'payment_method' => $sale->payment_method->value,
            'payment_status' => $sale->payment_status->value,
            'due_date' => $sale->due_date?->toDateString(),
            'paid_at' => $sale->paid_at,
            'created_at' => $sale->created_at,
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]),
            'payments' => $sale->payments->map(fn (SalePayment $payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
                'note' => $payment->note,
                'account' => $payment->account ? [
                    'id' => $payment->account->id,
                    'name' => $payment->account->name,
                    'type' => $payment->account->type,
                    'provider' => $payment->account->provider,
                    'account_number' => $payment->account->account_number,
                    'terminal_id' => $payment->account->terminal_id,
                    'is_active' => $payment->account->is_active,
                ] : null,
            ]),
        ];
    }

    private function permissionDenied(): JsonResponse
    {
        return $this->response('You are not allowed to record sales', JsonResponse::HTTP_FORBIDDEN);
    }
}
