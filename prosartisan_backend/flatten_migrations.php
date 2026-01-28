<?php

/**
 * Script pour déplacer toutes les migrations des sous-dossiers vers le dossier principal
 * Usage: php flatten_migrations.php
 */

$migrationsPath = __DIR__ . '/database/migrations';
$subdirectories = ['dispute', 'financial', 'identity', 'marketplace', 'reputation', 'worksite'];

echo "🔄 Déplacement des migrations des sous-dossiers...\n\n";

foreach ($subdirectories as $subdir) {
 $subdirPath = $migrationsPath . '/' . $subdir;

 if (!is_dir($subdirPath)) {
  echo "⚠️  Le dossier $subdir n'existe pas\n";
  continue;
 }

 $files = glob($subdirPath . '/*.php');

 if (empty($files)) {
  echo "📁 $subdir: Aucun fichier de migration\n";
  continue;
 }

 echo "📁 $subdir: " . count($files) . " fichier(s) trouvé(s)\n";

 foreach ($files as $file) {
  $filename = basename($file);
  $destination = $migrationsPath . '/' . $filename;

  // Vérifier si le fichier existe déjà
  if (file_exists($destination)) {
   echo "   ⚠️  $filename existe déjà dans le dossier principal\n";
   continue;
  }

  // Déplacer le fichier
  if (rename($file, $destination)) {
   echo "   ✅ $filename déplacé\n";
  } else {
   echo "   ❌ Erreur lors du déplacement de $filename\n";
  }
 }

 // Supprimer le dossier vide
 if (is_dir($subdirPath) && count(scandir($subdirPath)) == 2) { // . et ..
  rmdir($subdirPath);
  echo "   🗑️  Dossier $subdir supprimé\n";
 }

 echo "\n";
}

echo "✅ Terminé! Toutes les migrations sont maintenant dans le dossier principal.\n";
echo "💡 Vous pouvez maintenant exécuter: php artisan migrate\n";
