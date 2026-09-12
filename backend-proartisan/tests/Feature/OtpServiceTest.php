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

// ── Verrouillage après trop de tentatives ────────────────────────────────────
// L'OTP est le mécanisme de connexion. Un code à 4 chiffres n'offre que 10 000
// combinaisons : sans compteur porté par le code lui-même, le seul rempart
// était le throttle par IP, que contourne un parc de proxys.

test('un code errone consomme une tentative', function () {
    $phone = '2250707123456';
    $this->otpService->sendOtp($phone);

    $this->otpService->verifyOtp($phone, '0000');

    expect(Otp::latest()->first()->attempts)->toBe(1);
});

test('le code est brule apres cinq tentatives erronees', function () {
    $phone = '2250707123456';
    $code  = $this->otpService->sendOtp($phone);

    // 5 essais infructueux — on évite volontairement le bon code.
    $wrong = $code === '0000' ? '1111' : '0000';
    for ($i = 0; $i < 5; $i++) {
        expect($this->otpService->verifyOtp($phone, $wrong))->toBeFalse();
    }

    $otp = Otp::latest()->first();
    expect($otp->attempts)->toBe(5);
    expect($otp->used_at)->not->toBeNull();

    // Le cœur du correctif : même le bon code ne passe plus. L'attaquant doit
    // provoquer un nouvel envoi, ce qui le ramène à zéro à chaque fois.
    expect($this->otpService->verifyOtp($phone, $code))->toBeFalse();
});

test('le bon code reste accepte tant que le plafond n est pas atteint', function () {
    $phone = '2250707123456';
    $code  = $this->otpService->sendOtp($phone);

    $wrong = $code === '0000' ? '1111' : '0000';
    for ($i = 0; $i < 4; $i++) {
        expect($this->otpService->verifyOtp($phone, $wrong))->toBeFalse();
    }

    // Quatre erreurs ne doivent pas pénaliser un utilisateur légitime.
    expect($this->otpService->verifyOtp($phone, $code))->toBeTrue();
});

test('un nouvel envoi repart avec un compteur neuf', function () {
    $phone = '2250707123456';
    $this->otpService->sendOtp($phone);

    $this->otpService->verifyOtp($phone, '0000');
    $this->otpService->verifyOtp($phone, '0000');

    $second = $this->otpService->sendOtp($phone);

    $otp = Otp::latest('id')->first();
    expect($otp->attempts)->toBe(0);
    expect($this->otpService->verifyOtp($phone, $second))->toBeTrue();
});

test('it can send OTP via WhatsApp channel', function () {
    $phone = '2250707123456';

    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->with('WhatsApp OTP (log mode)', Mockery::on(function ($data) use ($phone) {
            return $data['recipient'] === $phone && str_contains($data['message'], 'code de vérification');
        }));

    $code = $this->otpService->sendOtp($phone, null, 'whatsapp');

    expect($code)->not->toBeNull();

    $this->assertDatabaseHas('otps', [
        'phone'   => $phone,
        'code'    => $code,
        'used_at' => null,
    ]);
});

test('it respects global otp_delivery_channel setting', function () {
    $phone = '2250707123456';

    // 1. WhatsApp only setting
    \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
        ['key' => 'otp_delivery_channel'],
        ['value' => 'whatsapp', 'type' => 'string']
    );

    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->with('WhatsApp OTP (log mode)', Mockery::any());

    $this->otpService->sendOtp($phone);

    // 2. Both setting
    \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
        ['key' => 'otp_delivery_channel'],
        ['value' => 'both', 'type' => 'string']
    );

    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->with('SMS (log mode)', Mockery::any());

    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->with('WhatsApp OTP (log mode)', Mockery::any());

    $this->otpService->sendOtp($phone);
});
