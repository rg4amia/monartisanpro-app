<?php

namespace App\Domain\Shared\Repositories;

use App\Domain\Shared\Models\SystemParameter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SystemParameterRepository
{
 public function findAll(): Collection;

 public function findByCategory(string $category): Collection;

 public function findByKey(string $key): ?SystemParameter;

 public function findPublic(): Collection;

 public function findEditable(): Collection;

 public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

 public function create(array $data): SystemParameter;

 public function update(SystemParameter $parameter, array $data): SystemParameter;

 public function delete(SystemParameter $parameter): bool;

 public function getValue(string $key, $default = null);

 public function setValue(string $key, $value, ?int $updatedBy = null): bool;

 public function getCategories(): Collection;

 public function bulkUpdate(array $parameters, ?int $updatedBy = null): bool;
}
