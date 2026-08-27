<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\StaffPermission;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canViewStock($request)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('business_id', $request->user()->business_id)],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'low_stock' => ['nullable', 'boolean'],
        ]);

        $branch = $this->targetBranch($request, $payload['branch_id'] ?? null);

        $products = Product::query()
            ->with(['branchStocks' => fn ($query) => $query->where('branch_id', $branch->id)])
            ->where('business_id', $request->user()->business_id)
            ->when($payload['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($payload['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereAny(['name', 'sku'], 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->productData($product, $branch));

        if ($request->boolean('low_stock')) {
            $products = $products->filter(fn (array $product) => $product['is_low_stock'])->values();
        }

        return response()->json([
            'branch' => $this->branchData($branch),
            'products' => $products,
        ], JsonResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_PRODUCTS->value)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->where('business_id', $request->user()->business_id)],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('business_id', $request->user()->business_id)],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
        ]);

        $branch = $this->targetBranch($request, $payload['branch_id'] ?? null);
        $quantity = (int) ($payload['quantity'] ?? 0);

        $product = DB::transaction(function () use ($payload, $request, $branch, $quantity) {
            $product = Product::create([
                'business_id' => $request->user()->business_id,
                'name' => $payload['name'],
                'sku' => $payload['sku'] ?? null,
                'category' => $payload['category'] ?? null,
                'unit' => $payload['unit'] ?? 'piece',
                'cost_price' => $payload['cost_price'],
                'selling_price' => $payload['selling_price'],
                'status' => ProductStatus::ACTIVE,
            ]);

            $stock = BranchProductStock::create([
                'business_id' => $request->user()->business_id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'reorder_level' => (int) ($payload['reorder_level'] ?? 0),
            ]);

            if ($quantity > 0) {
                $this->recordMovement($request, $product, $branch, StockMovementType::PURCHASE, $quantity, 0, $stock->quantity, 'Opening stock');
            }

            return $product->fresh();
        });

        return response()->json([
            'product' => $this->productData($product->load('branchStocks'), $branch),
        ], JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, string $productId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_PRODUCTS->value)) {
            return $this->permissionDenied();
        }

        $product = $this->findProduct($request, $productId);

        if (!$product) {
            return $this->response('Product not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'sku')->where('business_id', $request->user()->business_id)->ignore($product->id)],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'cost_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
        ]);

        $product->update($payload);

        return response()->json([
            'product' => $this->productData($product->fresh()->load('branchStocks'), $this->targetBranch($request)),
        ], JsonResponse::HTTP_OK);
    }

    public function archive(Request $request, string $productId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::MANAGE_PRODUCTS->value)) {
            return $this->permissionDenied();
        }

        $product = $this->findProduct($request, $productId);

        if (!$product) {
            return $this->response('Product not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $product->update([
            'status' => ProductStatus::ARCHIVED,
        ]);

        return response()->json([
            'product' => $this->productData($product->fresh()->load('branchStocks'), $this->targetBranch($request)),
        ], JsonResponse::HTTP_OK);
    }

    public function adjustStock(Request $request, string $productId): JsonResponse
    {
        if (!$request->user()->hasPermission(StaffPermission::ADJUST_STOCK->value)) {
            return $this->permissionDenied();
        }

        $product = $this->findProduct($request, $productId);

        if (!$product) {
            return $this->response('Product not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('business_id', $request->user()->business_id)],
            'quantity' => ['required', 'integer'],
            'type' => ['required', Rule::in([
                StockMovementType::PURCHASE->value,
                StockMovementType::RETURN->value,
                StockMovementType::DAMAGE->value,
                StockMovementType::CORRECTION->value,
                StockMovementType::MANUAL_ADJUSTMENT->value,
            ])],
            'reason' => ['nullable', 'string', 'max:255'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
        ]);

        $branch = $this->targetBranch($request, $payload['branch_id'] ?? null);
        $movementType = StockMovementType::from($payload['type']);
        $quantity = abs((int) $payload['quantity']);

        if ($quantity < 1) {
            return $this->response('Quantity must be at least 1', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stock = DB::transaction(function () use ($request, $product, $branch, $movementType, $quantity, $payload) {
            $stock = BranchProductStock::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = BranchProductStock::create([
                    'business_id' => $request->user()->business_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'quantity' => 0,
                    'reorder_level' => 0,
                ]);
            }

            $previous = $stock->quantity;
            $newQuantity = match ($movementType) {
                StockMovementType::DAMAGE => max(0, $previous - $quantity),
                StockMovementType::CORRECTION => $quantity,
                default => $previous + $quantity,
            };

            $stock->update([
                'quantity' => $newQuantity,
                'reorder_level' => $payload['reorder_level'] ?? $stock->reorder_level,
            ]);

            $this->recordMovement(
                $request,
                $product,
                $branch,
                $movementType,
                $quantity,
                $previous,
                $newQuantity,
                $payload['reason'] ?? null,
            );

            return $stock->fresh();
        });

        return response()->json([
            'product' => $this->productData($product->fresh()->load('branchStocks'), $branch),
            'stock' => $this->stockData($stock),
        ], JsonResponse::HTTP_OK);
    }

    public function movements(Request $request, string $productId): JsonResponse
    {
        if (!$this->canViewStock($request)) {
            return $this->permissionDenied();
        }

        $product = $this->findProduct($request, $productId);

        if (!$product) {
            return $this->response('Product not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('business_id', $request->user()->business_id)],
        ]);

        $branch = $this->targetBranch($request, $payload['branch_id'] ?? null);

        $movements = StockMovement::query()
            ->with('user')
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (StockMovement $movement) => $this->movementData($movement));

        return response()->json([
            'product' => $this->productData($product->load('branchStocks'), $branch),
            'movements' => $movements,
        ], JsonResponse::HTTP_OK);
    }

    private function canViewStock(Request $request): bool
    {
        return $request->user()->hasPermission(StaffPermission::VIEW_STOCK->value)
            || $request->user()->hasPermission(StaffPermission::ADJUST_STOCK->value)
            || $request->user()->hasPermission(StaffPermission::MANAGE_PRODUCTS->value);
    }

    private function targetBranch(Request $request, ?string $branchId = null): Branch
    {
        return Branch::query()
            ->where('business_id', $request->user()->business_id)
            ->when($branchId, fn ($query) => $query->where('id', $branchId))
            ->when(!$branchId, fn ($query) => $query->where('id', $request->user()->branch_id))
            ->first()
            ?? Branch::query()
                ->where('business_id', $request->user()->business_id)
                ->where('is_main', true)
                ->firstOrFail();
    }

    private function findProduct(Request $request, string $productId): ?Product
    {
        return Product::query()
            ->where('id', $productId)
            ->where('business_id', $request->user()->business_id)
            ->first();
    }

    private function recordMovement(
        Request $request,
        Product $product,
        Branch $branch,
        StockMovementType $type,
        int $quantity,
        int $previousQuantity,
        int $newQuantity,
        ?string $reason,
    ): StockMovement {
        return StockMovement::create([
            'business_id' => $request->user()->business_id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'type' => $type,
            'quantity' => $quantity,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $reason,
        ]);
    }

    public function allMovements(Request $request): JsonResponse
    {
        if (!$this->canViewStock($request)) {
            return $this->permissionDenied();
        }

        $payload = $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('business_id', $request->user()->business_id)],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
        ]);

        $branch = $this->targetBranch($request, $payload['branch_id'] ?? null);

        $movements = StockMovement::query()
            ->with(['product', 'user'])
            ->where('business_id', $request->user()->business_id)
            ->where('branch_id', $branch->id)
            ->when($payload['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (StockMovement $movement) => $this->movementData($movement));

        return response()->json([
            'branch' => $this->branchData($branch),
            'movements' => $movements,
        ], JsonResponse::HTTP_OK);
    }

    private function productData(Product $product, Branch $branch): array
    {
        $stock = $product->branchStocks->firstWhere('branch_id', $branch->id);

        return [
            'id' => $product->id,
            'business_id' => $product->business_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category,
            'unit' => $product->unit,
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price,
            'status' => $product->status->value,
            'branch_id' => $branch->id,
            'quantity' => $stock?->quantity ?? 0,
            'reorder_level' => $stock?->reorder_level ?? 0,
            'is_low_stock' => ($stock?->reorder_level ?? 0) > 0 && ($stock?->quantity ?? 0) <= ($stock?->reorder_level ?? 0),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }

    private function stockData(BranchProductStock $stock): array
    {
        return [
            'id' => $stock->id,
            'business_id' => $stock->business_id,
            'branch_id' => $stock->branch_id,
            'product_id' => $stock->product_id,
            'quantity' => $stock->quantity,
            'reorder_level' => $stock->reorder_level,
            'created_at' => $stock->created_at,
            'updated_at' => $stock->updated_at,
        ];
    }

    private function movementData(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type->value,
            'quantity' => $movement->quantity,
            'previous_quantity' => $movement->previous_quantity,
            'new_quantity' => $movement->new_quantity,
            'reason' => $movement->reason,
            'product' => $movement->product ? [
                'id' => $movement->product->id,
                'name' => $movement->product->name,
                'unit' => $movement->product->unit,
            ] : null,
            'user' => $movement->user ? [
                'id' => $movement->user->id,
                'name' => "{$movement->user->first_name} {$movement->user->last_name}",
            ] : null,
            'created_at' => $movement->created_at,
        ];
    }

    private function branchData(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'status' => $branch->status->value,
            'is_main' => $branch->is_main,
        ];
    }

    private function permissionDenied(): JsonResponse
    {
        return $this->response('You are not allowed to manage products or stock', JsonResponse::HTTP_FORBIDDEN);
    }
}
