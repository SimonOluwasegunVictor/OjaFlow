<?php

namespace App\Http\Controllers;

use App\Enums\BranchStatus;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->where('business_id', $request->user()->business_id)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => $this->branchData($branch));

        return response()->json([
            'branches' => $branches,
        ], JsonResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->where('business_id', $request->user()->business_id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = Branch::create([
            'business_id' => $request->user()->business_id,
            'name' => $payload['name'],
            'phone' => $payload['phone'] ?? null,
            'address' => $payload['address'] ?? null,
            'status' => BranchStatus::ACTIVE,
            'is_main' => false,
        ]);

        return response()->json([
            'branch' => $this->branchData($branch),
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, string $branchId): JsonResponse
    {
        $branch = $this->findBranch($request, $branchId);

        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            'branch' => $this->branchData($branch),
        ], JsonResponse::HTTP_OK);
    }

    public function update(Request $request, string $branchId): JsonResponse
    {
        $branch = $this->findBranch($request, $branchId);

        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('branches', 'name')->where('business_id', $request->user()->business_id)->ignore($branch->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $branch->update($payload);

        return response()->json([
            'branch' => $this->branchData($branch->fresh()),
        ], JsonResponse::HTTP_OK);
    }

    public function updateStatus(Request $request, string $branchId): JsonResponse
    {
        $branch = $this->findBranch($request, $branchId);

        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $request->validate([
            'status' => ['required', Rule::enum(BranchStatus::class)],
        ]);

        if ($branch->is_main && $payload['status'] !== BranchStatus::ACTIVE->value) {
            return response()->json([
                'message' => 'The main branch cannot be deactivated',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $branch->update([
            'status' => $payload['status'],
        ]);

        return response()->json([
            'branch' => $this->branchData($branch->fresh()),
        ], JsonResponse::HTTP_OK);
    }

    private function findBranch(Request $request, string $branchId): ?Branch
    {
        return Branch::query()
            ->where('id', $branchId)
            ->where('business_id', $request->user()->business_id)
            ->first();
    }

    private function branchData(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'phone' => $branch->phone,
            'address' => $branch->address,
            'status' => $branch->status->value,
            'is_main' => $branch->is_main,
            'created_at' => $branch->created_at,
            'updated_at' => $branch->updated_at,
        ];
    }
}
