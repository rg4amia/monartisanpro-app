<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service de gestion des photos géolocalisées
 * Utilisé pour les preuves de travail (jalons) et les matériaux (J-Codes)
 */
class PhotoService
{
    /**
     * Upload une photo avec métadonnées GPS
     *
     * @param UploadedFile $photo
     * @param float $latitude
     * @param float $longitude
     * @param string $type Type de photo ('jalon' ou 'jcode')
     * @param int|null $relatedId ID du jalon ou jcode
     * @return array ['url' => string, 'latitude' => float, 'longitude' => float, 'taken_at' => string, 'path' => string]
     * @throws \Exception
     */
    public function uploadGeolocatedPhoto(
        UploadedFile $photo,
        float $latitude,
        float $longitude,
        string $type = 'jalon',
        ?int $relatedId = null
    ): array {
        try {
            // Validation du fichier
            $this->validatePhotoFile($photo);

            // Validation des coordonnées GPS
            $this->validateCoordinates($latitude, $longitude);

            // Génération du nom de fichier unique
            $filename = $this->generatePhotoFilename($type, $relatedId);
            $extension = $photo->getClientOriginalExtension();
            $fullFilename = $filename . '.' . $extension;

            // Chemin de stockage
            $directory = $this->getStorageDirectory($type);
            $path = $directory . '/' . $fullFilename;

            // Upload vers storage (local ou S3)
            $storedPath = Storage::disk('public')->putFileAs(
                $directory,
                $photo,
                $fullFilename
            );

            if (!$storedPath) {
                throw new \Exception('Échec de l\'upload de la photo');
            }

            // URL publique
            $url = Storage::disk('public')->url($storedPath);

            Log::info('Photo géolocalisée uploadée', [
                'type' => $type,
                'related_id' => $relatedId,
                'path' => $storedPath,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return [
                'url' => $url,
                'path' => $storedPath,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'taken_at' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error('Erreur upload photo géolocalisée', [
                'type' => $type,
                'related_id' => $relatedId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Upload plusieurs photos géolocalisées (pour jalons)
     *
     * @param array $photos Array of ['photo' => UploadedFile, 'latitude' => float, 'longitude' => float]
     * @param string $type
     * @param int|null $relatedId
     * @return array
     */
    public function uploadMultipleGeolocatedPhotos(
        array $photos,
        string $type = 'jalon',
        ?int $relatedId = null
    ): array {
        $uploadedPhotos = [];

        foreach ($photos as $photoData) {
            try {
                $uploaded = $this->uploadGeolocatedPhoto(
                    $photoData['photo'],
                    $photoData['latitude'],
                    $photoData['longitude'],
                    $type,
                    $relatedId
                );

                $uploadedPhotos[] = $uploaded;
            } catch (\Exception $e) {
                Log::warning('Échec upload d\'une photo dans le lot', [
                    'error' => $e->getMessage(),
                ]);
                // Continue avec les autres photos
            }
        }

        return $uploadedPhotos;
    }

    /**
     * Valider un fichier photo
     *
     * @param UploadedFile $photo
     * @return void
     * @throws \Exception
     */
    protected function validatePhotoFile(UploadedFile $photo): void
    {
        // Taille max : 10 MB
        $maxSize = 10 * 1024 * 1024;

        if ($photo->getSize() > $maxSize) {
            throw new \Exception('La photo dépasse la taille maximale de 10 MB');
        }

        // Types MIME autorisés
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

        if (!in_array($photo->getMimeType(), $allowedMimes)) {
            throw new \Exception('Format de photo non supporté. Utilisez JPEG, PNG ou WebP');
        }

        // Vérifier que c'est bien une image
        if (!@getimagesize($photo->getRealPath())) {
            throw new \Exception('Le fichier n\'est pas une image valide');
        }
    }

    /**
     * Valider des coordonnées GPS
     *
     * @param float $latitude
     * @param float $longitude
     * @return void
     * @throws \Exception
     */
    protected function validateCoordinates(float $latitude, float $longitude): void
    {
        // Latitude: -90 à +90
        if ($latitude < -90 || $latitude > 90) {
            throw new \Exception('Latitude invalide (doit être entre -90 et 90)');
        }

        // Longitude: -180 à +180
        if ($longitude < -180 || $longitude > 180) {
            throw new \Exception('Longitude invalide (doit être entre -180 et 180)');
        }

        // Vérifier que ce n'est pas 0,0 (centre océan Atlantique - souvent erreur GPS)
        if ($latitude === 0.0 && $longitude === 0.0) {
            throw new \Exception('Coordonnées GPS invalides (0,0)');
        }
    }

    /**
     * Générer un nom de fichier unique pour la photo
     *
     * @param string $type
     * @param int|null $relatedId
     * @return string
     */
    protected function generatePhotoFilename(string $type, ?int $relatedId = null): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);

        if ($relatedId) {
            return "{$type}_{$relatedId}_{$timestamp}_{$random}";
        }

        return "{$type}_{$timestamp}_{$random}";
    }

    /**
     * Obtenir le répertoire de stockage selon le type
     *
     * @param string $type
     * @return string
     */
    protected function getStorageDirectory(string $type): string
    {
        return match ($type) {
            'jalon' => 'photos/jalons',
            'jcode' => 'photos/jcodes',
            'materiau' => 'photos/materiaux',
            default => 'photos/autres',
        };
    }

    /**
     * Supprimer une photo
     *
     * @param string $path
     * @return bool
     */
    public function deletePhoto(string $path): bool
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Erreur suppression photo', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Vérifier la distance entre deux points GPS (en mètres)
     * Utile pour valider que la photo a bien été prise au bon endroit
     *
     * @param float $lat1 Latitude point 1
     * @param float $lon1 Longitude point 1
     * @param float $lat2 Latitude point 2
     * @param float $lon2 Longitude point 2
     * @return float Distance en mètres
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Rayon de la Terre en mètres

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Valider que la photo a été prise dans le périmètre autorisé
     *
     * @param float $photoLat Latitude de la photo
     * @param float $photoLon Longitude de la photo
     * @param float $referenceLat Latitude de référence (ex: mission, fournisseur)
     * @param float $referenceLon Longitude de référence
     * @param float $maxDistanceMeters Distance maximale autorisée en mètres
     * @return bool
     */
    public function validatePhotoLocation(
        float $photoLat,
        float $photoLon,
        float $referenceLat,
        float $referenceLon,
        float $maxDistanceMeters = 100.0
    ): bool {
        $distance = $this->calculateDistance($photoLat, $photoLon, $referenceLat, $referenceLon);

        return $distance <= $maxDistanceMeters;
    }
}
