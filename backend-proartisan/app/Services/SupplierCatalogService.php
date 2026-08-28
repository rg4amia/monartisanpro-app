<?php

namespace App\Services;

use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierCatalogService
{
    public function approvedSuppliers(?string $search = null): Collection
    {
        return User::query()
            ->where('role', 'fournisseur')
            ->whereHas('fournisseurAgree', fn ($q) => $q->where('statut', 'agree'))
            ->with(['fournisseurAgree'])
            ->withCount(['supplierProducts' => fn ($q) => $q->where('is_active', true)])
            ->when($search, function ($query) use ($search) {
                $term = '%' . trim($search) . '%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('fournisseurAgree', fn ($agree) => $agree->where('nom_boutique', 'like', $term));
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function visibleProducts(User $supplier): Collection
    {
        $this->ensureApprovedSupplier($supplier);

        return $supplier->supplierProducts()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function ownProducts(User $supplier): Collection
    {
        $this->ensureApprovedSupplier($supplier);

        return $supplier->supplierProducts()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function createProduct(User $supplier, array $data): SupplierProduct
    {
        $this->ensureApprovedSupplier($supplier);
        $this->ensureUniqueSku($supplier, $data['sku'] ?? null);

        return $supplier->supplierProducts()->create([
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'unit_price' => $data['unit_price'],
            'stock_quantity' => $data['stock_quantity'],
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateProduct(User $supplier, SupplierProduct $product, array $data): SupplierProduct
    {
        $this->ensureOwnership($supplier, $product);
        $this->ensureUniqueSku($supplier, $data['sku'] ?? null, $product->id);

        $product->update([
            'name' => $data['name'] ?? $product->name,
            'sku' => array_key_exists('sku', $data) ? $data['sku'] : $product->sku,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'unit_price' => $data['unit_price'] ?? $product->unit_price,
            'stock_quantity' => $data['stock_quantity'] ?? $product->stock_quantity,
            'image_url' => array_key_exists('image_url', $data) ? $data['image_url'] : $product->image_url,
            'is_active' => $data['is_active'] ?? $product->is_active,
        ]);

        return $product->fresh();
    }

    public function archiveProduct(User $supplier, SupplierProduct $product): SupplierProduct
    {
        $this->ensureOwnership($supplier, $product);

        $product->update(['is_active' => false]);

        return $product->fresh();
    }

    public function decrementStockForServedItem(SupplierProduct $product, int $quantity): SupplierProduct
    {
        return DB::transaction(function () use ($product, $quantity) {
            $locked = SupplierProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => ["Stock insuffisant pour {$locked->name}. Disponible : {$locked->stock_quantity}."],
                ]);
            }

            $locked->decrement('stock_quantity', $quantity);

            return $locked->fresh();
        });
    }

    public function ensureApprovedSupplier(User $supplier): void
    {
        if ($supplier->role !== 'fournisseur' && $supplier->role !== 'admin') {
            throw ValidationException::withMessages([
                'supplier' => ['Ce compte n\'est pas un fournisseur.'],
            ]);
        }

        if ($supplier->role === 'admin') {
            return;
        }

        $approved = $supplier->fournisseurAgree()
            ->where('statut', 'agree')
            ->exists();

        if (! $approved) {
            throw ValidationException::withMessages([
                'supplier' => ['Le fournisseur doit être agréé avant de gérer un catalogue.'],
            ]);
        }
    }

    public function ensureOwnership(User $supplier, SupplierProduct $product): void
    {
        $this->ensureApprovedSupplier($supplier);

        if ($product->supplier_id !== $supplier->id) {
            throw ValidationException::withMessages([
                'product' => ['Cet article n\'appartient pas à ce fournisseur.'],
            ]);
        }
    }

    private function ensureUniqueSku(User $supplier, ?string $sku, ?int $ignoreId = null): void
    {
        if ($sku === null || trim($sku) === '') {
            return;
        }

        $exists = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->where('sku', $sku)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => ['Ce SKU existe déjà dans votre catalogue.'],
            ]);
        }
    }
}
