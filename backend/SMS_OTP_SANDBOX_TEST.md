# SMS OTP Service Testing Guide (Africa's Talking)

## Overview
Testing guide for SMS OTP functionality using Africa's Talking API for ProsArtisan platform.

## Prerequisites

1. **Africa's Talking Account**
   - Sign up at: https://africastalking.com/
   - For sandbox testing, use: https://account.africastalking.com/apps/sandbox
   - Get sandbox credentials from dashboard

2. **Update .env file**:
```env
AFRICASTALKING_USERNAME=sandbox
AFRICASTALKING_API_KEY=your_sandbox_api_key_here
AFRICASTALKING_SHORTCODE=40520
```

3. **Install Africa's Talking PHP SDK**:
```bash
composer require africastalking/africastalking
```

## Sandbox Setup

### Step 1: Create Sandbox App
1. Login to Africa's Talking
2. Navigate to Sandbox Apps
3. Create new sandbox application
4. Note the API Key

### Step 2: Add Test Phone Number
1. In sandbox dashboard, click "SMS"
2. Add your Ivorian phone number (+225...)
3. Verify phone ownership via test SMS

## Test Scenarios

### 1. Send OTP - Registration

**Test Code**:
```php
use App\Services\SmsService;

$smsService = app(SmsService::class);
$phone = '+2250123456789'; // Your test number
$code = $smsService->generateOtp(6);

$result = $smsService->sendOtp($phone, $code, 'registration');

if ($result) {
    echo "OTP sent: $code\n";
} else {
    echo "OTP send failed\n";
}
```

**Expected SMS Message**:
```
Votre code de vérification ProsArtisan: 123456. Valide 5 minutes. Ne le partagez pas.
```

**Verification**:
- [ ] SMS received within 30 seconds
- [ ] OTP code is 6 digits
- [ ] Message is in French
- [ ] Sender ID shows shortcode (40520)

---

### 2. Send OTP - Milestone Validation

**Test Code**:
```php
$code = $smsService->generateOtp(6);
$result = $smsService->sendOtp('+2250123456789', $code, 'milestone_validation');
```

**Expected SMS Message**:
```
Code de validation étape: 654321. Entrez ce code pour confirmer l'étape du projet. Valide 5 minutes.
```

**Verification**:
- [ ] SMS received
- [ ] Message mentions "étape du projet"
- [ ] Different code from previous test

---

### 3. Verify OTP - Success

**Test Code**:
```php
// After sending OTP
$phone = '+2250123456789';
$code = '123456'; // The code that was sent

$isValid = $smsService->verifyOtp($phone, $code);

if ($isValid) {
    echo "OTP verified successfully\n";
} else {
    echo "Invalid OTP\n";
}
```

**Verification**:
- [ ] Correct OTP returns true
- [ ] OTP is deleted from cache after verification (one-time use)
- [ ] Second attempt with same OTP fails

---

### 4. Verify OTP - Invalid Code

**Test Code**:
```php
$phone = '+2250123456789';
$wrongCode = '000000';

$isValid = $smsService->verifyOtp($phone, $wrongCode);

// Should return false
```

**Verification**:
- [ ] Invalid OTP returns false
- [ ] Error logged to storage/logs/sms.log
- [ ] No security exception thrown

---

### 5. OTP Expiry (5 Minutes)

**Test Code**:
```php
// Send OTP
$code = $smsService->generateOtp(6);
$smsService->sendOtp('+2250123456789', $code, 'login');

// Wait 6 minutes (or manipulate cache for testing)
sleep(360);

// Try to verify
$isValid = $smsService->verifyOtp('+2250123456789', $code);

// Should return false (expired)
```

**Verification**:
- [ ] OTP expires after 5 minutes
- [ ] Expired OTP verification returns false
- [ ] User receives appropriate error message

---

### 6. Rate Limiting (3 OTP per Hour)

**Test Code**:
```php
$phone = '+2250123456789';

// Send 3 OTPs (should succeed)
for ($i = 1; $i <= 3; $i++) {
    $code = $smsService->generateOtp(6);
    $result = $smsService->sendOtp($phone, $code, 'login');
    echo "Attempt $i: " . ($result ? 'Success' : 'Failed') . "\n";
    sleep(2);
}

// 4th attempt should fail (rate limit)
$code = $smsService->generateOtp(6);
$result = $smsService->sendOtp($phone, $code, 'login');
echo "Attempt 4: " . ($result ? 'Success' : 'Failed (Expected)') . "\n";
```

**Expected Output**:
```
Attempt 1: Success
Attempt 2: Success
Attempt 3: Success
Attempt 4: Failed (Expected)
```

**Verification**:
- [ ] First 3 SMS sent successfully
- [ ] 4th SMS blocked by rate limiter
- [ ] Rate limit warning logged
- [ ] User receives "Too many OTP requests" message

---

### 7. Phone Number Formatting

**Test Cases**:
```php
$testNumbers = [
    '0123456789',        // Local format
    '0123 45 67 89',     // With spaces
    '+225 01 23 45 67 89', // International with spaces
    '22501234567 89',       // Without + prefix
];

foreach ($testNumbers as $phone) {
    $code = $smsService->generateOtp(6);
    $result = $smsService->sendOtp($phone, $code, 'verification');
    echo "$phone -> " . ($result ? 'Success' : 'Failed') . "\n";
}
```

**All formats should be normalized to**: `+2250123456789`

**Verification**:
- [ ] Local format (0...) converted to +225...
- [ ] Spaces removed
- [ ] International format preserved
- [ ] All formats receive SMS

---

### 8. Send General Notification

**Test Code**:
```php
$phone = '+2250123456789';
$message = "Bonjour! Votre projet #123 a été accepté par l'artisan Jean. Vérifiez votre application ProsArtisan.";

$result = $smsService->sendNotification($phone, $message);
```

**Verification**:
- [ ] Notification SMS received
- [ ] Custom message delivered
- [ ] No OTP caching (not an OTP message)

---

### 9. Bulk SMS Notifications

**Test Code**:
```php
$phones = [
    '+2250123456789',
    '+2250987654321',
    '+2250555444333',
];

$message = "Mise à jour ProsArtisan: Une nouvelle fonctionnalité est disponible!";

$results = $smsService->sendBulk($phones, $message);

foreach ($results as $phone => $success) {
    echo "$phone: " . ($success ? 'Sent' : 'Failed') . "\n";
}
```

**Verification**:
- [ ] All registered phones receive SMS
- [ ] Unregistered phones logged as failed
- [ ] Success rate tracked

---

### 10. Account Balance Check

**Test Code**:
```php
$balance = $smsService->getBalance();

if ($balance) {
    echo "Balance: {$balance['balance']} {$balance['currency']}\n";
} else {
    echo "Failed to fetch balance\n";
}
```

**Verification**:
- [ ] Balance returned in USD
- [ ] Balance > 0 in sandbox
- [ ] Production balance monitored for alerts

---

## Integration with AuthController

### Update AuthController to Use SmsService

**File**: `app/Http/Controllers/Api/V1/AuthController.php`

**Method**: `sendPhoneOtp()`

```php
use App\Services\SmsService;

public function sendPhoneOtp(Request $request)
{
    $validated = $request->validate([
        'phone' => 'required|string',
        'purpose' => 'in:registration,login,password_reset',
    ]);

    $smsService = app(SmsService::class);

    // Generate 6-digit OTP
    $code = $smsService->generateOtp(6);

    // Send via SMS
    $sent = $smsService->sendOtp(
        $validated['phone'],
        $code,
        $validated['purpose'] ?? 'verification'
    );

    if ($sent) {
        return response()->json([
            'message' => 'OTP envoyé avec succès',
            'expires_in' => 300, // 5 minutes in seconds
        ]);
    }

    return response()->json([
        'error' => 'Échec de l\'envoi du code OTP'
    ], 500);
}
```

**Method**: `verifyPhoneOtp()`

```php
public function verifyPhoneOtp(Request $request)
{
    $validated = $request->validate([
        'phone' => 'required|string',
        'code' => 'required|digits:6',
    ]);

    $smsService = app(SmsService::class);

    if ($smsService->verifyOtp($validated['phone'], $validated['code'])) {
        // Mark phone as verified
        $user = User::where('phone', $validated['phone'])->first();

        if ($user) {
            $user->update(['phone_verified_at' => now()]);
        }

        return response()->json([
            'message' => 'Téléphone vérifié avec succès',
            'verified' => true,
        ]);
    }

    return response()->json([
        'error' => 'Code OTP invalide ou expiré',
        'verified' => false,
    ], 400);
}
```

---

## Integration with MilestoneController

### Send OTP for Milestone Validation

**File**: `app/Http/Controllers/Api/V1/MilestoneController.php`

**Method**: `generateOtp()`

```php
public function generateOtp(Request $request, $milestoneId)
{
    $milestone = Milestone::findOrFail($milestoneId);

    // Verify client owns the project
    if ($milestone->project->client_id !== $request->user()->id) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $smsService = app(SmsService::class);
    $code = $smsService->generateOtp(6);

    // Send OTP to client's phone
    $sent = $smsService->sendOtp(
        $request->user()->phone,
        $code,
        'milestone_validation'
    );

    if ($sent) {
        return response()->json([
            'message' => 'Code de validation envoyé',
            'expires_in' => 300,
        ]);
    }

    return response()->json(['error' => 'Échec envoi OTP'], 500);
}
```

---

## Logging Verification

**Check SMS logs**:
```bash
tail -f storage/logs/sms.log
```

**Expected log entries**:

1. **OTP Sent Successfully**:
```json
{
    "level": "INFO",
    "message": "OTP sent successfully",
    "phone": "+2250123456789",
    "code": "123456",
    "purpose": "registration",
    "messageId": "ATXid_xxx",
    "cost": "XOF 25"
}
```

2. **OTP Verified**:
```json
{
    "level": "INFO",
    "message": "OTP verified successfully",
    "phone": "+2250123456789"
}
```

3. **Rate Limit Exceeded**:
```json
{
    "level": "WARNING",
    "message": "OTP rate limit exceeded",
    "phone": "+2250123456789",
    "purpose": "login"
}
```

4. **Send Failed**:
```json
{
    "level": "ERROR",
    "message": "OTP send failed",
    "phone": "+2250123456789",
    "purpose": "verification",
    "response": {...}
}
```

---

## Test Checklist

### Prerequisites
- [ ] Africa's Talking account created
- [ ] Sandbox API key obtained
- [ ] Test phone number (+225) added to sandbox
- [ ] Africa's Talking SDK installed
- [ ] .env configured with sandbox credentials

### OTP Functionality
- [ ] Generate 6-digit OTP
- [ ] Send OTP for registration
- [ ] Send OTP for login
- [ ] Send OTP for milestone validation
- [ ] Send OTP for password reset
- [ ] Verify valid OTP
- [ ] Reject invalid OTP
- [ ] OTP expires after 5 minutes
- [ ] One-time use (OTP deleted after verification)

### Rate Limiting
- [ ] Allow 3 OTP per hour per phone
- [ ] Block 4th OTP request
- [ ] Rate limit resets after 1 hour

### Phone Formatting
- [ ] Format local numbers (0...) to +225...
- [ ] Handle numbers with spaces
- [ ] Handle international format

### Notifications
- [ ] Send general notification SMS
- [ ] Send bulk SMS to multiple phones

### Logging
- [ ] All OTP sends logged
- [ ] Verification attempts logged
- [ ] Rate limit violations logged
- [ ] Errors logged with context

### Integration
- [ ] AuthController uses SmsService
- [ ] MilestoneController uses SmsService
- [ ] API endpoints tested with Postman

---

## Common Issues & Troubleshooting

### Issue: SMS not received in sandbox
**Cause**: Phone number not added to sandbox
**Fix**: Add your phone to sandbox whitelist in dashboard

### Issue: Invalid credentials error
**Cause**: Wrong API key or username
**Fix**: Double-check .env matches sandbox dashboard

### Issue: "Insufficient balance" error
**Cause**: Sandbox credit depleted (rare)
**Fix**: Contact Africa's Talking support for sandbox credits

### Issue: Rate limit always blocks
**Cause**: Cache not clearing
**Fix**: Run `php artisan cache:clear`

---

## Production Deployment

### Before Going Live:
1. **Switch to Production Credentials**:
   ```env
   AFRICASTALKING_USERNAME=your_production_username
   AFRICASTALKING_API_KEY=your_production_api_key
   AFRICASTALKING_SHORTCODE=your_shortcode
   ```

2. **Purchase Shortcode**:
   - Apply for dedicated shortcode (e.g., "PROSART")
   - Costs ~$100-500 one-time + monthly fees
   - Improves deliverability and brand recognition

3. **Top Up Balance**:
   - Monitor balance daily
   - Set up auto-top-up when balance < $10
   - Configure low balance alerts

4. **Update Rate Limits** (if needed):
   ```php
   // Allow 5 OTP per hour for production
   RateLimiter::attempt($rateLimitKey, 5, function () {}, 3600);
   ```

---

## Cost Estimates

**Sandbox**: Free (limited to whitelisted numbers)

**Production** (Côte d'Ivoire):
- OTP SMS: ~25-35 XOF ($0.04-0.06) per message
- Shortcode: ~$100-500 one-time + $50/month
- Expected monthly cost (1000 users):
  - 1000 registrations × 2 OTP = 2000 SMS
  - 500 milestone validations = 500 SMS
  - Total: 2500 SMS × $0.05 = **~$125/month**

---

## Estimated Testing Time

- Sandbox setup: 30 minutes
- OTP send/verify tests: 1 hour
- Rate limiting tests: 30 minutes
- Integration with controllers: 1 hour
- Documentation review: 30 minutes

**Total**: ~3.5 hours for comprehensive SMS testing

---

*Last updated: 2026-02-16*
*Africa's Talking API Version: v3*
