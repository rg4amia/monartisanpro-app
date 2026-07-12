<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierProduct\StoreSupplierProductRequest;
use App\Http\Requests\SupplierProduct\UpdateSupplierProductRequest;
use App\Http\Resources\FournisseurResource;
use App\Http\Resources\SupplierProductResource;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\SupplierCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCatalogController extends Controller
{
    public function __construct(private SupplierCatalogService $catalogService) {}

    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = $this->catalogService->approvedSuppliers($request->query('search'));

        return response()->json([
            'success' => true,
            'data' => FournisseurResource::collection($suppliers),
        ]);
    }

    public function supplierProducts(User $user): JsonResponse
    {
        $products = $this->catalogService->visibleProducts($user);

        return response()->json([
            'success' => true,
            'data' => SupplierProductResource::collection($products),
        ]);
    }

    public function myProducts(Request $request): JsonResponse
    {
        $products = $this->catalogService->ownProducts($request->user());

        return response()->json([
            'success' => true,
            'data' => SupplierProductResource::collection($products),
        ]);
    }

    public function store(StoreSupplierProductRequest $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $product = $this->catalogService->createProduct($request->user(), $request->validated());

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'success' => true,
            'message' => 'Article ajouté au catalogue.',
            'data' => new SupplierProductResource($product),
        ], 201);
    }

    public function update(UpdateSupplierProductRequest $request, SupplierProduct $supplierProduct): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $product = $this->catalogService->updateProduct(
            $request->user(),
            $supplierProduct,
            $request->validated(),
        );

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour.',
            'data' => new SupplierProductResource($product),
        ]);
    }

    public function destroy(Request $request, SupplierProduct $supplierProduct): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $product = $this->catalogService->archiveProduct($request->user(), $supplierProduct);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'success' => true,
            'message' => 'Article retiré du catalogue.',
            'data' => new SupplierProductResource($product),
        ]);
    }
}
