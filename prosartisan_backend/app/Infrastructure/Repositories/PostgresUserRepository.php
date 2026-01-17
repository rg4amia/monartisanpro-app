<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\Client\Client;
use App\Domain\Identity\Models\Fournisseur\Fournisseur;
use App\Domain\Identity\Models\ReferentZone\ReferentZone;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL implementation of UserRepository
 *
 * Handles persistence of User aggregates and their specialized types
 * (Artisan, Client, Fournisseur, ReferentZone) using PostgreSQL with PostGIS
 */
class PostgresUserRepository implements UserRepository
{
    /**
     * Save a user entity
     *
     * Handles both insert and update operations
     * For Artisan and Fournisseur, also saves profile data
     */
    public function save(User $user): void
    {
        DB::transaction(function () use ($user) {
            $userData = [
                'id' => $user->getId()->toString(),
                'email' => $user->getEmail()->toString(),
                'password_hash' => $user->getPassword()->toString(),
                'user_type' => $user->getType()->toString(),
                'account_status' => $user->getStatus()->toString(),
                'phone_number' => null,
                'failed_login_attempts' => $user->getFailedLoginAttempts(),
                'locked_until' => $user->getLockedUntil()?->format('Y-m-d H:i:s'),
                'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];

            // Check if user exists
            $exists = DB::table('users')->where('id', $userData['id'])->exists();

            if ($exists) {
                // Update existing user
                DB::table('users')
                    ->where('id', $userData['id'])
                    ->update($userData);
            } else {
                // Insert new user
                $userData['created_at'] = $user->getCreatedAt()->format('Y-m-d H:i:s');
                DB::table('users')->insert($userData);
            }

            // Handle type-specific data
            if ($user instanceof Artisan) {
                $this->saveArtisanProfile($user);
            } elseif ($user instanceof Client) {
                $this->saveClientProfile($user);
            } elseif ($user instanceof Fournisseur) {
                $this->saveFournisseurProfile($user);
            } elseif ($user instanceof ReferentZone) {
                $this->saveReferentZoneProfile($user);
            }

            // Handle KYC documents if present
            if ($user->hasKYCDocuments()) {
                $this->saveKYCVerification($user);
            }
        });
    }

    /**
     * Find a user by ID
     */
    public function findById(UserId $id): ?User
    {
        $userData = DB::table('users')
            ->where('id', $id->toString())
            ->first();

        if (!$userData) {
            return null;
        }

        return $this->hydrateUser($userData);
    }

    /**
     * Find a user by email
     */
    public function findByEmail(Email $email): ?User
    {
        $userData = DB::table('users')
            ->where('email', $email->toString())
            ->first();

        if (!$userData) {
            return null;
        }

        return $this->hydrateUser($userData);
    }

    /**
     * Find artisans near a location using PostGIS
     *
     * Uses ST_DWithin for efficient spatial queries
     */
    public function findArtisansNearLocation(GPS_Coordinates $location, float $radiusKm): array
    {
        $radiusMeters = $radiusKm * 1000;

        $results = DB::select("
            SELECT
                u.id,
                u.email,
                u.password_hash,
                u.user_type,
                u.account_status,
                u.phone_number,
                u.failed_login_attempts,
                u.locked_until,
                u.created_at,
                u.updated_at,
                ap.trade_category,
                ST_Y(ap.location::geometry) as latitude,
                ST_X(ap.location::geometry) as longitude,
                ap.is_kyc_verified,
                ap.kyc_documents,
                ST_Distance(
                    ap.location,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) as distance_meters
            FROM users u
            INNER JOIN artisan_profiles ap ON u.id = ap.user_id
            WHERE u.user_type = 'ARTISAN'
            AND u.account_status = 'ACTIVE'
            AND ST_DWithin(
                ap.location,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                ?
            )
            ORDER BY distance_meters ASC
        ", [
            $location->getLongitude(),
            $location->getLatitude(),
            $location->getLongitude(),
            $location->getLatitude(),
            $radiusMeters
        ]);

        $artisans = [];
        foreach ($results as $row) {
            $artisans[] = $this->hydrateArtisan($row);
        }

        return $artisans;
    }

    /**
     * Delete a user
     */
    public function delete(UserId $id): void
    {
        DB::transaction(function () use ($id) {
            // Cascade deletes are handled by database foreign key constraints
            DB::table('users')->where('id', $id->toString())->delete();
        });
    }

    /**
     * Save artisan profile data
     */
    private function saveArtisanProfile(Artisan $artisan): void
    {
        $profileData = [
            'user_id' => $artisan->getId()->toString(),
            'trade_category' => $artisan->getCategory()->toString(),
            'location' => DB::raw(sprintf(
                "ST_SetSRID(ST_MakePoint(%f, %f), 4326)::geography",
                $artisan->getLocation()->getLongitude(),
                $artisan->getLocation()->getLatitude()
            )),
            'is_kyc_verified' => $artisan->isKYCVerified(),
            'kyc_documents' => $artisan->hasKYCDocuments()
                ? json_encode($this->serializeKYCDocuments($artisan->getKYCDocuments()))
                : null,
            'updated_at' => $artisan->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        $exists = DB::table('artisan_profiles')
            ->where('user_id', $artisan->getId()->toString())
            ->exists();

        if ($exists) {
            DB::table('artisan_profiles')
                ->where('user_id', $artisan->getId()->toString())
                ->update($profileData);
        } else {
            $profileData['id'] = DB::raw('gen_random_uuid()');
            $profileData['created_at'] = $artisan->getCreatedAt()->format('Y-m-d H:i:s');
            DB::table('artisan_profiles')->insert($profileData);
        }

        // Update phone number in users table
        DB::table('users')
            ->where('id', $artisan->getId()->toString())
            ->update(['phone_number' => $artisan->getPhoneNumber()->toString()]);
    }

    /**
     * Save client profile data
     */
    private function saveClientProfile(Client $client): void
    {
        // Update phone number in users table
        DB::table('users')
            ->where('id', $client->getId()->toString())
            ->update([
                'phone_number' => $client->getPhoneNumber()->toString(),
            ]);

        // Note: Client-specific data like preferred_payment_method could be stored
        // in a separate client_profiles table if needed in the future
    }

    /**
     * Save fournisseur profile data
     */
    private function saveFournisseurProfile(Fournisseur $fournisseur): void
    {
        $profileData = [
            'user_id' => $fournisseur->getId()->toString(),
            'business_name' => $fournisseur->getBusinessName(),
            'shop_location' => DB::raw(sprintf(
                "ST_SetSRID(ST_MakePoint(%f, %f), 4326)::geography",
                $fournisseur->getShopLocation()->getLongitude(),
                $fournisseur->getShopLocation()->getLatitude()
            )),
            'updated_at' => $fournisseur->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        $exists = DB::table('fournisseur_profiles')
            ->where('user_id', $fournisseur->getId()->toString())
            ->exists();

        if ($exists) {
            DB::table('fournisseur_profiles')
                ->where('user_id', $fournisseur->getId()->toString())
                ->update($profileData);
        } else {
            $profileData['id'] = DB::raw('gen_random_uuid()');
            $profileData['created_at'] = $fournisseur->getCreatedAt()->format('Y-m-d H:i:s');
            DB::table('fournisseur_profiles')->insert($profileData);
        }

        // Update phone number in users table
        DB::table('users')
            ->where('id', $fournisseur->getId()->toString())
            ->update(['phone_number' => $fournisseur->getPhoneNumber()->toString()]);
    }

    /**
     * Save referent zone profile data
     */
    private function saveReferentZoneProfile(ReferentZone $referent): void
    {
        // Update phone number in users table
        DB::table('users')
            ->where('id', $referent->getId()->toString())
            ->update([
                'phone_number' => $referent->getPhoneNumber()->toString(),
            ]);

        // Save referent zone profile
        $profileData = [
            'user_id' => $referent->getId()->toString(),
            'zone' => $referent->getZone(),
            'coverage_area' => DB::raw(sprintf(
                "ST_SetSRID(ST_MakePoint(%f, %f), 4326)::geography",
                $referent->getCoverageArea()->getLongitude(),
                $referent->getCoverageArea()->getLatitude()
            )),
            'updated_at' => $referent->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        $exists = DB::table('referent_zone_profiles')
            ->where('user_id', $referent->getId()->toString())
            ->exists();

        if ($exists) {
            DB::table('referent_zone_profiles')
                ->where('user_id', $referent->getId()->toString())
                ->update($profileData);
        } else {
            $profileData['id'] = DB::raw('gen_random_uuid()');
            $profileData['created_at'] = $referent->getCreatedAt()->format('Y-m-d H:i:s');
            DB::table('referent_zone_profiles')->insert($profileData);
        }
    }

    /**
     * Save KYC verification data
     */
    private function saveKYCVerification(User $user): void
    {
        $kycDocs = $user->getKYCDocuments();
        if (!$kycDocs) {
            return;
        }

        $kycData = [
            'user_id' => $user->getId()->toString(),
            'id_type' => $kycDocs->getIdType(),
            'id_number' => $kycDocs->getIdNumber(),
            'id_document_url' => $kycDocs->getIdDocumentUrl(),
            'selfie_url' => $kycDocs->getSelfieUrl(),
            'verification_status' => $user->isActive() ? 'VERIFIED' : 'PENDING',
            'verified_at' => $user->isActive() ? $user->getUpdatedAt()->format('Y-m-d H:i:s') : null,
        ];

        $exists = DB::table('kyc_verifications')
            ->where('user_id', $user->getId()->toString())
            ->exists();

        if ($exists) {
            DB::table('kyc_verifications')
                ->where('user_id', $user->getId()->toString())
                ->update($kycData);
        } else {
            $kycData['id'] = DB::raw('gen_random_uuid()');
            $kycData['created_at'] = $kycDocs->getSubmittedAt()->format('Y-m-d H:i:s');
            DB::table('kyc_verifications')->insert($kycData);
        }
    }

    /**
     * Hydrate a User entity from database row
     */
    private function hydrateUser(object $userData): User
    {
        $userType = UserType::fromString($userData->user_type);

        // Load type-specific data based on user type
        switch ($userType->toString()) {
            case 'ARTISAN':
                return $this->hydrateArtisanFromUserId($userData);
            case 'CLIENT':
                return $this->hydrateClient($userData);
            case 'FOURNISSEUR':
                return $this->hydrateFournisseurFromUserId($userData);
            case 'REFERENT_ZONE':
                return $this->hydrateReferentZone($userData);
            default:
                return $this->hydrateBaseUser($userData);
        }
    }

    /**
     * Hydrate base User entity
     */
    private function hydrateBaseUser(object $userData): User
    {
        $kycDocuments = $this->loadKYCDocuments($userData->id);

        $user = new User(
            UserId::fromString($userData->id),
            Email::fromString($userData->email),
            HashedPassword::fromHash($userData->password_hash),
            UserType::fromString($userData->user_type),
            AccountStatus::fromString($userData->account_status),
            $kycDocuments,
            new DateTime($userData->created_at),
            new DateTime($userData->updated_at)
        );

        // Restore failed login attempts and lock status
        $this->restoreLoginState($user, $userData);

        return $user;
    }

    /**
     * Hydrate Artisan entity from user ID
     */
    private function hydrateArtisanFromUserId(object $userData): Artisan
    {
        $profileData = DB::table('artisan_profiles')
            ->where('user_id', $userData->id)
            ->first();

        if (!$profileData) {
            throw new \RuntimeException("Artisan profile not found for user {$userData->id}");
        }

        return $this->hydrateArtisan((object) array_merge(
            (array) $userData,
            (array) $profileData
        ));
    }

    /**
     * Hydrate Artisan entity from combined data
     */
    private function hydrateArtisan(object $data): Artisan
    {
        $kycDocuments = $this->loadKYCDocuments($data->id ?? $data->user_id);

        $artisan = new Artisan(
            UserId::fromString($data->id ?? $data->user_id),
            Email::fromString($data->email),
            HashedPassword::fromHash($data->password_hash),
            PhoneNumber::fromString($data->phone_number ?? ''),
            TradeCategory::fromString($data->trade_category),
            new GPS_Coordinates(
                (float) $data->latitude,
                (float) $data->longitude
            ),
            (bool) $data->is_kyc_verified,
            AccountStatus::fromString($data->account_status),
            $kycDocuments,
            new DateTime($data->created_at),
            new DateTime($data->updated_at)
        );

        $this->restoreLoginState($artisan, $data);

        return $artisan;
    }

    /**
     * Hydrate Client entity
     */
    private function hydrateClient(object $userData): Client
    {
        $client = new Client(
            UserId::fromString($userData->id),
            Email::fromString($userData->email),
            HashedPassword::fromHash($userData->password_hash),
            PhoneNumber::fromString($userData->phone_number ?? ''),
            null, // preferred_payment_method - could be loaded from client_profiles table
            AccountStatus::fromString($userData->account_status),
            new DateTime($userData->created_at),
            new DateTime($userData->updated_at)
        );

        $this->restoreLoginState($client, $userData);

        return $client;
    }

    /**
     * Hydrate Fournisseur entity from user ID
     */
    private function hydrateFournisseurFromUserId(object $userData): Fournisseur
    {
        $profileData = DB::table('fournisseur_profiles')
            ->select([
                'business_name',
                'ST_Y(shop_location::geometry) as latitude',
                'ST_X(shop_location::geometry) as longitude',
            ])
            ->where('user_id', $userData->id)
            ->first();

        if (!$profileData) {
            throw new \RuntimeException("Fournisseur profile not found for user {$userData->id}");
        }

        $kycDocuments = $this->loadKYCDocuments($userData->id);

        // Check if KYC verified from kyc_verifications table
        $isKYCVerified = DB::table('kyc_verifications')
            ->where('user_id', $userData->id)
            ->where('verification_status', 'VERIFIED')
            ->exists();

        $fournisseur = new Fournisseur(
            UserId::fromString($userData->id),
            Email::fromString($userData->email),
            HashedPassword::fromHash($userData->password_hash),
            PhoneNumber::fromString($userData->phone_number ?? ''),
            $profileData->business_name,
            new GPS_Coordinates(
                (float) $profileData->latitude,
                (float) $profileData->longitude
            ),
            $isKYCVerified,
            AccountStatus::fromString($userData->account_status),
            $kycDocuments,
            new DateTime($userData->created_at),
            new DateTime($userData->updated_at)
        );

        $this->restoreLoginState($fournisseur, $userData);

        return $fournisseur;
    }

    /**
     * Hydrate ReferentZone entity
     */
    private function hydrateReferentZone(object $userData): ReferentZone
    {
        $profileData = DB::table('referent_zone_profiles')
            ->select([
                'zone',
                'ST_Y(coverage_area::geometry) as latitude',
                'ST_X(coverage_area::geometry) as longitude',
            ])
            ->where('user_id', $userData->id)
            ->first();

        if (!$profileData) {
            throw new \RuntimeException("ReferentZone profile not found for user {$userData->id}");
        }

        $referent = new ReferentZone(
            UserId::fromString($userData->id),
            Email::fromString($userData->email),
            HashedPassword::fromHash($userData->password_hash),
            PhoneNumber::fromString($userData->phone_number ?? ''),
            new GPS_Coordinates(
                (float) $profileData->latitude,
                (float) $profileData->longitude
            ),
            $profileData->zone,
            AccountStatus::fromString($userData->account_status),
            new DateTime($userData->created_at),
            new DateTime($userData->updated_at)
        );

        $this->restoreLoginState($referent, $userData);

        return $referent;
    }

    /**
     * Load KYC documents for a user
     */
    private function loadKYCDocuments(string $userId): ?KYCDocuments
    {
        $kycData = DB::table('kyc_verifications')
            ->where('user_id', $userId)
            ->first();

        if (!$kycData) {
            return null;
        }

        return new KYCDocuments(
            $kycData->id_type,
            $kycData->id_number,
            $kycData->id_document_url,
            $kycData->selfie_url,
            new DateTime($kycData->created_at)
        );
    }

    /**
     * Restore login state (failed attempts and lock) using reflection
     */
    private function restoreLoginState(User $user, object $userData): void
    {
        if (isset($userData->failed_login_attempts) && $userData->failed_login_attempts > 0) {
            $reflection = new \ReflectionClass($user);

            $failedAttemptsProperty = $reflection->getProperty('failedLoginAttempts');
            $failedAttemptsProperty->setAccessible(true);
            $failedAttemptsProperty->setValue($user, (int) $userData->failed_login_attempts);

            if (isset($userData->locked_until) && $userData->locked_until) {
                $lockedUntilProperty = $reflection->getProperty('lockedUntil');
                $lockedUntilProperty->setAccessible(true);
                $lockedUntilProperty->setValue($user, new DateTime($userData->locked_until));
            }
        }
    }

    /**
     * Serialize KYC documents to array
     */
    private function serializeKYCDocuments(?KYCDocuments $docs): ?array
    {
        if (!$docs) {
            return null;
        }

        return [
            'id_type' => $docs->getIdType(),
            'id_number' => $docs->getIdNumber(),
            'id_document_url' => $docs->getIdDocumentUrl(),
            'selfie_url' => $docs->getSelfieUrl(),
            'submitted_at' => $docs->getSubmittedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
