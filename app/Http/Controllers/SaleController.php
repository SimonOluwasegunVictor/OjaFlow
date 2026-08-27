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
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
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
        ]);

        $paymentMethod = SalePaymentMethod::from($payload['payment_method']);
        $amountPaid = (float) ($payload['amount_paid'] ?? 0);
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

        $sale = DB::transaction(function () use ($request, $payload, $branch, $cart, $amountPaid, $paymentMethod) {
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

            return $sale->fresh(['customer', 'user', 'items']);
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

    private function targetBranch(Request $request): Branch
    {
        return Branch::query()
            ->where('business_id', $request->user()->business_id)
            ->where('id', $request->user()->branch_id)
            ->first()
            ?? Branch::query()
                ->where('business_id', $request->user()->business_id)
                ->where('is_main', true)
                ->firstOrFail();
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
        ];
    }

    private function permissionDenied(): JsonResponse
    {
        return $this->response('You are not allowed to record sales', JsonResponse::HTTP_FORBIDDEN);
    }
}
