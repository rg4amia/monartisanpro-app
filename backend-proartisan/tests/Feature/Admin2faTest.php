<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Google2faService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Admin2faTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_cryptography_generation_and_validation(): void
    {
        $service = new Google2faService();

        // 1. Génération
        $secret = $service->generateSecretKey();
        $this->assertEquals(16, strlen($secret));
        
        // 2. Vérification que les caractères sont dans l'alphabet Base32
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);

        // 3. QR Code URL
        $url = $service->getQrCodeUrl('test@prosartisan.ci', $secret);
        $this->assertStringContainsString('otpauth://totp/', $url);
        $this->assertStringContainsString('secret=' . $secret, $url);
        $this->assertStringContainsString('issuer=ProsArtisan', $url);

        // 4. Génération et validation immédiate du code actuel
        $code = $service->getCurrentCode($secret);
        $this->assertEquals(6, strlen($code));
        $this->assertTrue($service->verifyCode($secret, $code));
    }

    public function test_login_redirects_to_2fa_verification(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'phone' => '+2250102030405',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'kyc_status' => 'actif',
        ]);

        $response = $this->post('/admin/login', [
            'identifier' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.login.verify-2fa'));
        $this->assertEquals($admin->id, session('admin_2fa_user_id'));
    }

    public function test_cannot_access_verify_2fa_page_without_session(): void
    {
        $response = $this->get('/admin/login/verify-2fa');
        $response->assertRedirect(route('admin.login'));
    }

    public function test_verify_2fa_page_shows_qr_code_for_unconfigured_user(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'phone' => '+2250102030405',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'kyc_status' => 'actif',
            'google_2fa_secret' => null, // Non configuré
        ]);

        $response = $this->withSession(['admin_2fa_user_id' => $admin->id])
            ->get('/admin/login/verify-2fa');

        $response->assertStatus(200);
        
        // Vérifie qu'on passe à Inertia les données d'enrôlement
        $inertiaPage = $response->original->getData()['page'];
        $this->assertEquals('admin/auth/verify-2fa', $inertiaPage['component']);
        $this->assertFalse($inertiaPage['props']['isConfigured']);
        $this->assertNotNull($inertiaPage['props']['secret']);
        $this->assertNotNull($inertiaPage['props']['qrCodeUrl']);
        $this->assertNotNull(session('admin_2fa_temp_secret'));
    }

    public function test_2fa_activation_saves_secret_and_logs_in(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'phone' => '+2250102030405',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'kyc_status' => 'actif',
            'google_2fa_secret' => null,
        ]);

        $service = new Google2faService();
        $tempSecret = $service->generateSecretKey();
        $code = $service->getCurrentCode($tempSecret);

        $response = $this->withSession([
            'admin_2fa_user_id' => $admin->id,
            'admin_2fa_temp_secret' => $tempSecret,
        ])->post('/admin/login/verify-2fa', [
            'code' => $code,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        
        $admin->refresh();
        $this->assertEquals($tempSecret, $admin->google_2fa_secret);
        $this->assertNull(session('admin_2fa_user_id'));
    }

    public function test_2fa_verification_logs_in_configured_user(): void
    {
        $service = new Google2faService();
        $secret = $service->generateSecretKey();

        $admin = User::create([
            'name' => 'Admin Test',
            'phone' => '+2250102030405',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'kyc_status' => 'actif',
            'google_2fa_secret' => $secret,
        ]);

        $code = $service->getCurrentCode($secret);

        $response = $this->withSession([
            'admin_2fa_user_id' => $admin->id,
        ])->post('/admin/login/verify-2fa', [
            'code' => $code,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('admin_2fa_user_id'));
    }

    public function test_2fa_verification_fails_with_invalid_code(): void
    {
        $service = new Google2faService();
        $secret = $service->generateSecretKey();

        $admin = User::create([
            'name' => 'Admin Test',
            'phone' => '+2250102030405',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'kyc_status' => 'actif',
            'google_2fa_secret' => $secret,
        ]);

        $response = $this->withSession([
            'admin_2fa_user_id' => $admin->id,
        ])->post('/admin/login/verify-2fa', [
            'code' => '000000', // Code incorrect
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
