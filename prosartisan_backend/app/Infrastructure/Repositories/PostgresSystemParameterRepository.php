<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Shared\Models\SystemParameter;
use App\Domain\Shared\Repositories\SystemParameterRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostgresSystemParameterRepository implements SystemParameterRepository
{
 public function findAll(): Collection
 {
  return SystemParameter::orderBy('category')->orderBy('label')->get();
 }

 public function findByCategory(string $category): Collection
 {
  return SystemParameter::byCategory($category)->orderBy('label')->get();
 }

 public function findByKey(string $key): ?SystemParameter
 {
  return SystemParameter::where('key', $key)->first();
 }

 public function findPublic(): Collection
 {
  return SystemParameter::public()->orderBy('category')->orderBy('label')->get();
 }

 public function findEditable(): Collection
 {
  return SystemParameter::editable()->orderBy('category')->orderBy('label')->get();
 }

 public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
 {
  $query = SystemParameter::with('updatedBy:id,email');

  if (!empty($filters['category'])) {
   $query->byCategory($filters['category']);
  }

  if (!empty($filters['search'])) {
   $search = $filters['search'];
   $query->where(function ($q) use ($search) {
    $q->where('label', 'like', "%{$search}%")
     ->orWhere('key', 'like', "%{$search}%")
     ->orWhere('description', 'like', "%{$search}%");
   });
  }

  if (isset($filters['is_editable'])) {
   $query->where('is_editable', $filters['is_editable']);
  }

  if (isset($filters['is_public'])) {
   $query->where('is_public', $filters['is_public']);
  }

  return $query->orderBy('category')->orderBy('label')->paginate($perPage);
 }

 public function create(array $data): SystemParameter
 {
  return SystemParameter::create($data);
 }

 public function update(SystemParameter $parameter, array $data): SystemParameter
 {
  $parameter->update($data);
  return $parameter->fresh();
 }

 public function delete(SystemParameter $parameter): bool
 {
  return $parameter->delete();
 }

 public function getValue(string $key, $default = null)
 {
  $parameter = $this->findByKey($key);
  return $parameter ? $parameter->getTypedValue() : $default;
 }

 public function setValue(string $key, $value, ?int $updatedBy = null): bool
 {
  $parameter = $this->findByKey($key);

  if (!$parameter) {
   return false;
  }

  $parameter->setTypedValue($value);
  $parameter->updated_by = $updatedBy;

  return $parameter->save();
 }

 public function getCategories(): Collection
 {
  return SystemParameter::select('category')
   ->distinct()
   ->orderBy('category')
   ->pluck('category');
 }

 public function bulkUpdate(array $parameters, ?int $updatedBy = null): bool
 {
  return DB::transaction(function () use ($parameters, $updatedBy) {
   foreach ($parameters as $key => $value) {
    $this->setValue($key, $value, $updatedBy);
   }
   return true;
  });
 }
}
