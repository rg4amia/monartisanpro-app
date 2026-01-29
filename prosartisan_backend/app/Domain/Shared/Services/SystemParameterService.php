<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\SystemParameter;
use App\Domain\Shared\Repositories\SystemParameterRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SystemParameterService
{
 public function __construct(
  private SystemParameterRepository $repository
 ) {}

 public function getAllParameters(): Collection
 {
  return $this->repository->findAll();
 }

 public function getParametersByCategory(string $category): Collection
 {
  return $this->repository->findByCategory($category);
 }

 public function getPublicParameters(): Collection
 {
  return $this->repository->findPublic();
 }

 public function getEditableParameters(): Collection
 {
  return $this->repository->findEditable();
 }

 public function paginateParameters(array $filters = [], int $perPage = 20): LengthAwarePaginator
 {
  return $this->repository->paginate($filters, $perPage);
 }

 public function getParameter(string $key): ?SystemParameter
 {
  return $this->repository->findByKey($key);
 }

 public function getParameterValue(string $key, $default = null)
 {
  return $this->repository->getValue($key, $default);
 }

 public function createParameter(array $data, ?int $updatedBy = null): SystemParameter
 {
  $this->validateParameterData($data);

  $data['updated_by'] = $updatedBy;

  return $this->repository->create($data);
 }

 public function updateParameter(SystemParameter $parameter, array $data, ?int $updatedBy = null): SystemParameter
 {
  if (!$parameter->is_editable) {
   throw new \InvalidArgumentException('Ce paramètre ne peut pas être modifié.');
  }

  $this->validateParameterData($data, $parameter);

  $data['updated_by'] = $updatedBy;

  return $this->repository->update($parameter, $data);
 }

 public function updateParameterValue(string $key, $value, ?int $updatedBy = null): bool
 {
  $parameter = $this->repository->findByKey($key);

  if (!$parameter) {
   throw new \InvalidArgumentException("Paramètre '{$key}' introuvable.");
  }

  if (!$parameter->is_editable) {
   throw new \InvalidArgumentException('Ce paramètre ne peut pas être modifié.');
  }

  $this->validateParameterValue($parameter, $value);

  return $this->repository->setValue($key, $value, $updatedBy);
 }

 public function bulkUpdateParameters(array $parameters, ?int $updatedBy = null): bool
 {
  // Validate all parameters first
  foreach ($parameters as $key => $value) {
   $parameter = $this->repository->findByKey($key);
   if ($parameter) {
    $this->validateParameterValue($parameter, $value);
   }
  }

  return $this->repository->bulkUpdate($parameters, $updatedBy);
 }

 public function deleteParameter(SystemParameter $parameter): bool
 {
  if (!$parameter->is_editable) {
   throw new \InvalidArgumentException('Ce paramètre ne peut pas être supprimé.');
  }

  return $this->repository->delete($parameter);
 }

 public function getCategories(): Collection
 {
  return $this->repository->getCategories();
 }

 public function getParameterTypes(): array
 {
  return [
   'string' => 'Texte',
   'integer' => 'Nombre entier',
   'float' => 'Nombre décimal',
   'boolean' => 'Booléen (Oui/Non)',
   'json' => 'JSON',
   'email' => 'Email',
   'url' => 'URL',
   'percentage' => 'Pourcentage',
  ];
 }

 private function validateParameterData(array $data, ?SystemParameter $parameter = null): void
 {
  $rules = [
   'key' => 'required|string|max:255|regex:/^[a-z0-9_\.]+$/',
   'value' => 'required',
   'type' => 'required|in:string,integer,float,boolean,json,email,url,percentage',
   'category' => 'required|string|max:255',
   'label' => 'required|string|max:255',
   'description' => 'nullable|string',
   'is_public' => 'boolean',
   'is_editable' => 'boolean',
   'validation_rules' => 'nullable|array',
  ];

  if (!$parameter) {
   $rules['key'] .= '|unique:system_parameters,key';
  }

  $validator = Validator::make($data, $rules);

  if ($validator->fails()) {
   throw new ValidationException($validator);
  }

  // Validate the value according to its type
  if (isset($data['value']) && isset($data['type'])) {
   $this->validateValueByType($data['value'], $data['type']);
  }
 }

 private function validateParameterValue(SystemParameter $parameter, $value): void
 {
  $this->validateValueByType($value, $parameter->type);

  // Apply custom validation rules if defined
  if ($parameter->validation_rules) {
   $validator = Validator::make(['value' => $value], [
    'value' => $parameter->validation_rules
   ]);

   if ($validator->fails()) {
    throw new ValidationException($validator);
   }
  }
 }

 private function validateValueByType($value, string $type): void
 {
  switch ($type) {
   case 'integer':
    if (!is_numeric($value) || (int) $value != $value) {
     throw new \InvalidArgumentException('La valeur doit être un nombre entier.');
    }
    break;

   case 'float':
    if (!is_numeric($value)) {
     throw new \InvalidArgumentException('La valeur doit être un nombre.');
    }
    break;

   case 'boolean':
    if (!in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'oui', 'non'])) {
     throw new \InvalidArgumentException('La valeur doit être un booléen (true/false, 1/0, yes/no).');
    }
    break;

   case 'json':
    if (!is_array($value) && json_decode($value) === null) {
     throw new \InvalidArgumentException('La valeur doit être un JSON valide.');
    }
    break;

   case 'email':
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
     throw new \InvalidArgumentException('La valeur doit être une adresse email valide.');
    }
    break;

   case 'url':
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
     throw new \InvalidArgumentException('La valeur doit être une URL valide.');
    }
    break;

   case 'percentage':
    if (!is_numeric($value) || $value < 0 || $value > 100) {
     throw new \InvalidArgumentException('La valeur doit être un pourcentage entre 0 et 100.');
    }
    break;
  }
 }
}
