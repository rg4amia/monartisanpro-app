<?php

namespace App\Http\Controllers\Backoffice;

use App\Domain\Shared\Models\SystemParameter;
use App\Domain\Shared\Services\SystemParameterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ParametersController extends Controller
{
 public function __construct(
  private SystemParameterService $parameterService
 ) {}

 public function index(Request $request)
 {
  $parameters = $this->parameterService->paginateParameters(
   $request->only(['category', 'search', 'is_editable', 'is_public']),
   20
  );

  $categories = $this->parameterService->getCategories();
  $stats = $this->getParameterStats();

  return Inertia::render('Backoffice/Parameters/Index', [
   'parameters' => $parameters,
   'filters' => $request->only(['category', 'search', 'is_editable', 'is_public']),
   'categories' => $categories,
   'stats' => $stats,
   'parameterTypes' => $this->parameterService->getParameterTypes(),
  ]);
 }

 public function show(SystemParameter $parameter)
 {
  $parameter->load('updatedBy:id,email');

  return Inertia::render('Backoffice/Parameters/Show', [
   'parameter' => $parameter,
   'parameterTypes' => $this->parameterService->getParameterTypes(),
  ]);
 }

 public function store(Request $request)
 {
  try {
   $parameter = $this->parameterService->createParameter(
    $request->all(),
    Auth::id()
   );

   return redirect()
    ->route('backoffice.parameters.show', $parameter)
    ->with('success', 'Paramètre créé avec succès.');
  } catch (ValidationException $e) {
   return back()->withErrors($e->errors())->withInput();
  } catch (\Exception $e) {
   return back()->with('error', $e->getMessage())->withInput();
  }
 }

 public function update(Request $request, SystemParameter $parameter)
 {
  try {
   $updatedParameter = $this->parameterService->updateParameter(
    $parameter,
    $request->all(),
    Auth::id()
   );

   return redirect()
    ->route('backoffice.parameters.show', $updatedParameter)
    ->with('success', 'Paramètre mis à jour avec succès.');
  } catch (ValidationException $e) {
   return back()->withErrors($e->errors())->withInput();
  } catch (\Exception $e) {
   return back()->with('error', $e->getMessage())->withInput();
  }
 }

 public function updateValue(Request $request, SystemParameter $parameter)
 {
  try {
   $this->parameterService->updateParameterValue(
    $parameter->key,
    $request->input('value'),
    Auth::id()
   );

   return back()->with('success', 'Valeur du paramètre mise à jour avec succès.');
  } catch (\Exception $e) {
   return back()->with('error', $e->getMessage());
  }
 }

 public function bulkUpdate(Request $request)
 {
  try {
   $parameters = $request->input('parameters', []);

   $this->parameterService->bulkUpdateParameters($parameters, Auth::id());

   return back()->with('success', 'Paramètres mis à jour avec succès.');
  } catch (\Exception $e) {
   return back()->with('error', $e->getMessage());
  }
 }

 public function destroy(SystemParameter $parameter)
 {
  try {
   $this->parameterService->deleteParameter($parameter);

   return redirect()
    ->route('backoffice.parameters.index')
    ->with('success', 'Paramètre supprimé avec succès.');
  } catch (\Exception $e) {
   return back()->with('error', $e->getMessage());
  }
 }

 public function export(Request $request)
 {
  $parameters = $this->parameterService->getAllParameters();

  $filename = 'parametres_systeme_' . now()->format('Y-m-d_H-i-s') . '.csv';

  $headers = [
   'Content-Type' => 'text/csv',
   'Content-Disposition' => "attachment; filename=\"{$filename}\"",
  ];

  $callback = function () use ($parameters) {
   $file = fopen('php://output', 'w');

   // UTF-8 BOM for Excel compatibility
   fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

   // Headers
   fputcsv($file, [
    'Clé',
    'Libellé',
    'Valeur',
    'Type',
    'Catégorie',
    'Description',
    'Public',
    'Modifiable',
    'Dernière modification',
    'Modifié par',
   ], ';');

   foreach ($parameters as $parameter) {
    fputcsv($file, [
     $parameter->key,
     $parameter->label,
     $parameter->value,
     $parameter->type,
     $parameter->category,
     $parameter->description,
     $parameter->is_public ? 'Oui' : 'Non',
     $parameter->is_editable ? 'Oui' : 'Non',
     $parameter->updated_at?->format('d/m/Y H:i:s'),
     $parameter->updatedBy?->email,
    ], ';');
   }

   fclose($file);
  };

  return response()->stream($callback, 200, $headers);
 }

 public function categories()
 {
  $categories = $this->parameterService->getCategories();

  $categoryStats = [];
  foreach ($categories as $category) {
   $parameters = $this->parameterService->getParametersByCategory($category);
   $categoryStats[] = [
    'name' => $category,
    'count' => $parameters->count(),
    'editable_count' => $parameters->where('is_editable', true)->count(),
    'public_count' => $parameters->where('is_public', true)->count(),
   ];
  }

  return Inertia::render('Backoffice/Parameters/Categories', [
   'categories' => $categoryStats,
  ]);
 }

 private function getParameterStats(): array
 {
  $allParameters = $this->parameterService->getAllParameters();

  return [
   'total' => $allParameters->count(),
   'editable' => $allParameters->where('is_editable', true)->count(),
   'public' => $allParameters->where('is_public', true)->count(),
   'categories' => $allParameters->groupBy('category')->count(),
   'by_type' => $allParameters->groupBy('type')->map->count(),
   'recently_updated' => $allParameters->where('updated_at', '>=', now()->subDays(7))->count(),
  ];
 }
}
