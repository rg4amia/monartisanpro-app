<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SystemParameter extends Model
{
 protected $table = 'system_parameters';

 protected $fillable = [
  'key',
  'value',
  'type',
  'category',
  'label',
  'description',
  'is_public',
  'is_editable',
  'validation_rules',
  'updated_by',
 ];

 protected $casts = [
  'is_public' => 'boolean',
  'is_editable' => 'boolean',
  'validation_rules' => 'array',
 ];

 public function updatedBy(): BelongsTo
 {
  return $this->belongsTo(User::class, 'updated_by');
 }

 /**
  * Get the typed value of the parameter
  */
 public function getTypedValue()
 {
  return match ($this->type) {
   'integer' => (int) $this->value,
   'float' => (float) $this->value,
   'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
   'json' => json_decode($this->value, true),
   default => $this->value,
  };
 }

 /**
  * Set the value with proper type casting
  */
 public function setTypedValue($value): void
 {
  $this->value = match ($this->type) {
   'json' => json_encode($value),
   'boolean' => $value ? '1' : '0',
   default => (string) $value,
  };
 }

 /**
  * Scope for public parameters
  */
 public function scopePublic($query)
 {
  return $query->where('is_public', true);
 }

 /**
  * Scope for editable parameters
  */
 public function scopeEditable($query)
 {
  return $query->where('is_editable', true);
 }

 /**
  * Scope by category
  */
 public function scopeByCategory($query, string $category)
 {
  return $query->where('category', $category);
 }
}
