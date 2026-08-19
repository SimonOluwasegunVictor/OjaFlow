<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\StaffPermission;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        return response()->json([
            'cart' => $this->cartData($this->activeCart($request)),
        ], JsonResponse::HTTP_OK);
    }

    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('business_id', $request->user()->business_id)],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $cart = $this->activeCart($request);
        $cart->update([
            'customer_id' => $payload['customer_id'] ?? null,
            'discount' => (float) ($payload['discount'] ?? 0),
        ]);

        $this->recalculate($cart);

        return response()->json([
            'cart' => $this->cartData($cart->fresh(['items.product.branchStocks', 'customer'])),
        ], JsonResponse::HTTP_OK);
    }

    public function addItem(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $request->user()->business_id)],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = DB::transaction(function () use ($request, $payload) {
            $cart = $this->activeCart($request);
            $branch = $this->targetBranch($request);
            $product = Product::query()
                ->where('business_id', $request->user()->business_id)
                ->where('id', $payload['product_id'])
                ->where('status', ProductStatus::ACTIVE)
                ->firstOrFail();

            $stock = $this->productStock($request, $branch, $product);
            $quantityToAdd = (int) ($payload['quantity'] ?? 1);
            $existing = $cart->items()->where('product_id', $product->id)->first();
            $newQuantity = ($existing?->quantity ?? 0) + $quantityToAdd;

            if ($newQuantity > $stock->quantity) {
                abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, "{$product->name} does not have enough stock");
            }

            if ($existing) {
                $existing->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $product->selling_price,
                    'line_total' => (float) $product->selling_price * $newQuantity,
                ]);
            } else {
                $cart->items()->create([
                    'business_id' => $request->user()->business_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $newQuantity,
                    'unit_price' => $product->selling_price,
                    'line_total' => (float) $product->selling_price * $newQuantity,
                ]);
            }

            $this->recalculate($cart);

            return $cart->fresh(['items.product.branchStocks', 'customer']);
        });

        return response()->json([
            'cart' => $this->cartData($cart),
        ], JsonResponse::HTTP_OK);
    }

    public function updateItem(Request $request, string $cartItemId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $cart = DB::transaction(function () use ($request, $cartItemId, $payload) {
            $cart = $this->activeCart($request);
            $item = $this->cartItem($request, $cart, $cartItemId);

            if ((int) $payload['quantity'] <= 0) {
                $item->delete();
                $this->recalculate($cart);

                return $cart->fresh(['items.product.branchStocks', 'customer']);
            }

            $stock = $this->productStock($request, $this->targetBranch($request), $item->product);

            if ((int) $payload['quantity'] > $stock->quantity) {
                abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, "{$item->product_name} does not have enough stock");
            }

            $item->update([
                'quantity' => (int) $payload['quantity'],
                'line_total' => (float) $item->unit_price * (int) $payload['quantity'],
            ]);

            $this->recalculate($cart);

            return $cart->fresh(['items.product.branchStocks', 'customer']);
        });

        return response()->json([
            'cart' => $this->cartData($cart),
        ], JsonResponse::HTTP_OK);
    }

    public function removeItem(Request $request, string $cartItemId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        $cart = $this->activeCart($request);
        $item = $this->cartItem($request, $cart, $cartItemId);
        $item->delete();
        $this->recalculate($cart);

        return response()->json([
            'cart' => $this->cartData($cart->fresh(['items.product.branchStocks', 'customer'])),
        ], JsonResponse::HTTP_OK);
    }

    public function clear(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::RECORD_SALES->value)) {
            return $this->permissionDenied();
        }

        $cart = $this->activeCart($request);
        $cart->items()->delete();
        $cart->update([
            'customer_id' => null,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
        ]);

        return response()->json([
            'cart' => $this->cartData($cart->fresh(['items.product.branchStocks', 'customer'])),
        ], JsonResponse::HTTP_OK);
    }

    private function activeCart(Request $request): Cart
    {
        $branch = $this->targetBranch($request);

        return Cart::query()
            ->with(['items.product.branchStocks', 'customer'])
            ->firstOrCreate([
                'business_id' => $request->user()->business_id,
                'branch_id' => $branch->id,
                'user_id' => $request->user()->id,
                'status' => 'active',
            ], [
                'customer_id' => null,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
            ]);
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

    private function productStock(Request $request, Branch $branch, Product $product): BranchProductStock
    {
        return BranchProductStock::query()
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
    }

    private function cartItem(Request $request, Cart $cart, string $cartItemId): CartItem
    {
        return CartItem::query()
            ->with('product')
            ->where('business_id', $request->user()->business_id)
            ->where('cart_id', $cart->id)
            ->where('id', $cartItemId)
            ->firstOrFail();
    }

    private function recalculate(Cart $cart): void
    {
        $subtotal = (float) $cart->items()->sum('line_total');
        $discount = min((float) $cart->discount, $subtotal);

        $cart->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
        ]);
    }

    public function cartData(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'business_id' => $cart->business_id,
            'branch_id' => $cart->branch_id,
            'user_id' => $cart->user_id,
            'customer_id' => $cart->customer_id,
            'status' => $cart->status,
            'subtotal' => $cart->subtotal,
            'discount' => $cart->discount,
            'total' => $cart->total,
            'checked_out_at' => $cart->checked_out_at,
            'customer' => $cart->customer ? [
                'id' => $cart->customer->id,
                'name' => $cart->customer->name,
                'phone' => $cart->customer->phone,
            ] : null,
            'items' => $cart->items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'available_stock' => $item->product?->branchStocks?->firstWhere('branch_id', $cart->branch_id)?->quantity,
            ]),
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
        ];
    }

    private function permissionDenied(): JsonResponse
    {
        return response()->json([
            'message' => 'You are not allowed to record sales',
        ], JsonResponse::HTTP_FORBIDDEN);
    }
}
