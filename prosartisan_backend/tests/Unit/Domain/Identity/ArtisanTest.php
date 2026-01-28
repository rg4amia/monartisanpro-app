<?php

namespace Tests\Unit\Domain\Identity;

use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use PHPUnit\Framework\TestCase;

class ArtisanTest extends TestCase
{
    public function test_can_create_artisan(): void
    {
        $artisan = Artisan::createArtisan(
            new Email('artisan@example.com'),
            HashedPassword::fromPlainText('password123'),
            new PhoneNumber('+22507123456'),
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3600, -4.0083)
        );

        $this->assertInstanceOf(Artisan::class, $artisan);
        $this->assertFalse($artisan->isKYCVerified());
        $this->assertTrue($artisan->getCategory()->isPlumber());
    }

    public function test_unverified_artisan_cannot_accept_missions(): void
    {
        $artisan = Artisan::createArtisan(
            new Email('artisan@example.com'),
            HashedPassword::fromPlainText('password123'),
            new PhoneNumber('+22507123456'),
            TradeCategory::ELECTRICIAN(),
            new GPS_Coordinates(5.3600, -4.0083)
        );

        $this->assertFalse($artisan->canAcceptMissions());
    }

    public function test_verified_artisan_can_accept_missions(): void
    {
        $artisan = Artisan::createArtisan(
            new Email('artisan@example.com'),
            HashedPassword::fromPlainText('password123'),
            new PhoneNumber('+22507123456'),
            TradeCategory::MASON(),
            new GPS_Coordinates(5.3600, -4.0083)
        );

        $kycDocs = new KYCDocuments(
            'CNI',
            '123456789',
            '/path/to/id.jpg',
            '/path/to/selfie.jpg'
        );

        $artisan->verifyKYC($kycDocs);

        $this->assertTrue($artisan->isKYCVerified());
        $this->assertTrue($artisan->canAcceptMissions());
    }

    public function test_can_update_artisan_location(): void
    {
        $artisan = Artisan::createArtisan(
            new Email('artisan@example.com'),
            HashedPassword::fromPlainText('password123'),
            new PhoneNumber('+22507123456'),
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3600, -4.0083)
        );

        $newLocation = new GPS_Coordinates(5.3700, -4.0100);
        $artisan->updateLocation($newLocation);

        $this->assertEquals($newLocation, $artisan->getLocation());
    }

    public function test_can_get_blurred_location(): void
    {
        $originalLocation = new GPS_Coordinates(5.3600, -4.0083);
        $artisan = Artisan::createArtisan(
            new Email('artisan@example.com'),
            HashedPassword::fromPlainText('password123'),
            new PhoneNumber('+22507123456'),
            TradeCategory::PLUMBER(),
            $originalLocation
        );

        $blurredLocation = $artisan->getBlurredLocation();

        // Blurred location should be different from original
        $this->assertNotEquals($originalLocation, $blurredLocation);

        // But should be within 50m
        $distance = $originalLocation->distanceTo($blurredLocation);
        $this->assertLessThanOrEqual(50, $distance);
    }

    public function test_can_change_trade_category(): void
    {
        $artisan = Artisan::createArtisan(
            new Email('artisan@example.com'),
            HashedPassword::fromPlainText('password123'),
            new PhoneNumber('+22507123456'),
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3600, -4.0083)
        );

        $artisan->changeCategory(TradeCategory::ELECTRICIAN());

        $this->assertTrue($artisan->getCategory()->isElectrician());
    }
}
