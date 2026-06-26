<?php

use App\Models\Otp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->otpService = app(OtpService::class);
});

test('it can generate and save otp in database without existing user', function () {
    $phone = '2250707123456';

    $code = $this->otpService->sendOtp($phone);

    expect($code)->not->toBeNull();
    expect(strlen($code))->toBe(4);

    $this->assertDatabaseHas('otps', [
        'phone'      => $phone,
        'user_id'    => null,
        'code'       => $code,
        'used_at'    => null,
    ]);

    $otp = Otp::latest()->first();
    expect($otp->expires_at->isAfter(now()))->toBeTrue();
});

test('it links otp to user if user exists', function () {
    $phone = '2250707123456';
    $user  = User::factory()->create(['phone' => $phone]);

    $code = $this->otpService->sendOtp($phone);

    $this->assertDatabaseHas('otps', [
        'phone'      => $phone,
        'user_id'    => $user->id,
        'code'       => $code,
        'used_at'    => null,
    ]);
});

test('it can verify a valid otp and mark it as used', function () {
    $phone = '2250707123456';
    $code  = $this->otpService->sendOtp($phone);

    $result = $this->otpService->verifyOtp($phone, $code);

    expect($result)->toBeTrue();

    $otp = Otp::latest()->first();
    expect($otp->used_at)->not->toBeNull();
    expect($otp->used_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('it rejects verification with wrong code', function () {
    $phone = '2250707123456';
    $this->otpService->sendOtp($phone);

    $result = $this->otpService->verifyOtp($phone, '9999');

    expect($result)->toBeFalse();

    $otp = Otp::latest()->first();
    expect($otp->used_at)->toBeNull();
});

test('it rejects expired otp', function () {
    $phone = '2250707123456';
    $code  = $this->otpService->sendOtp($phone);

    // Voyage dans le temps de 6 minutes (TTL est de 5 minutes)
    Carbon::setTestNow(now()->addMinutes(6));

    $result = $this->otpService->verifyOtp($phone, $code);

    expect($result)->toBeFalse();

    $otp = Otp::latest()->first();
    expect($otp->used_at)->toBeNull();

    Carbon::setTestNow(); // Reset
});

test('it cannot reuse a verified/used otp', function () {
    $phone = '2250707123456';
    $code  = $this->otpService->sendOtp($phone);

    // Première validation : OK
    $firstResult = $this->otpService->verifyOtp($phone, $code);
    expect($firstResult)->toBeTrue();

    // Deuxième validation : Ko
    $secondResult = $this->otpService->verifyOtp($phone, $code);
    expect($secondResult)->toBeFalse();
});

test('it respects action parameter if provided', function () {
    $phone = '2250707123456';
    $code  = $this->otpService->sendOtp($phone, 'login');

    // Vérification avec action différente : Ko
    expect($this->otpService->verifyOtp($phone, $code, 'register'))->toBeFalse();

    // Vérification avec la bonne action : OK
    expect($this->otpService->verifyOtp($phone, $code, 'login'))->toBeTrue();
});
