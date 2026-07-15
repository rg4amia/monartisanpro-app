# SMS Pro Africa Integration

This document describes the SMS Pro Africa API integration for ProsArtisan.

## Configuration

Add the following to your `.env` file:

```env
SMS_PROVIDER=smspro # Use 'log' for development, 'smspro' for production
SMS_API_TOKEN=1227|Gjd4N2x6qRYdnwWybpkJfoA87LbCFAFnvpNK2NPwa4861d63 
SMS_BASE_URL=https://app.smspro.africa/api/v3
SMS_SENDER_ID=ProsArtisan          # Max 11 characters
```

## Usage

### Send SMS via Service

```php
use App\Services\SmsService;

$smsService = app(SmsService::class);

// Send to single recipient
$result = $smsService->send(
    recipient: '2250707123456',
    message: 'Hello from ProsArtisan!',
    senderId: 'ProsArtisan'
);

// Send to multiple recipients
$result = $smsService->send(
    recipient: ['2250707123456', '2250708234567'],
    message: 'Bulk message',
    senderId: 'ProsArtisan'
);

// Schedule SMS
$result = $smsService->send(
    recipient: '2250707123456',
    message: 'Scheduled message',
    senderId: 'ProsArtisan',
    scheduleTime: '2025-03-10 14:00'
);
```

### Send OTP

```php
use App\Services\OtpService;

$otpService = app(OtpService::class);

// Generate and send OTP
$code = $otpService->sendOtp('2250707123456');

// Verify OTP
$isValid = $otpService->verifyOtp('2250707123456', '1234');
```

### API Endpoints

#### Send SMS

```bash
POST /api/v1/sms/send
Authorization: Bearer {token}
Content-Type: application/json

{
  "recipient": "2250707123456",
  "message": "Test message",
  "sender_id": "ProsArtisan",
  "schedule_time": "2025-03-10 14:00"  // Optional
}
```

#### View SMS

```bash
GET /api/v1/sms/{uid}
Authorization: Bearer {token}
```

#### View All Messages

```bash
GET /api/v1/sms
Authorization: Bearer {token}
```

## Development Mode

When `SMS_PROVIDER=log`, all SMS messages are logged instead of sent. Check `storage/logs/laravel.log` for output.

## Production Mode

Set `SMS_PROVIDER=smspro` to enable actual SMS sending via SMS Pro Africa API.

## Available Methods

### SmsService

- `send(string|array $recipient, string $message, string $senderId, ?string $scheduleTime)` - Send SMS
- `sendCampaign(string|array $contactListId, string $message, string $senderId)` - Send campaign
- `view(string $uid)` - View SMS details
- `viewAll()` - View all messages
- `viewCampaign(string $uid)` - View campaign details
- `sendOtp(string $phone, string $code)` - Send OTP code

### OtpService

- `sendOtp(string $phone)` - Generate and send OTP
- `verifyOtp(string $phone, string $code)` - Verify OTP
- `ttlSeconds()` - Get OTP TTL in seconds

## Error Handling

All methods return an array with `status` and `data` or `message`:

```php
[
    'status' => 'success',
    'data' => [...] // SMS details
]

// Or on error:
[
    'status' => 'error',
    'message' => 'Error description'
]
```

## Testing

```bash
# Test SMS sending (log mode)
curl -X POST http://backend-proartisan.test/api/v1/sms/send \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient": "2250707123456",
    "message": "Test from ProsArtisan"
  }'
```

## Notes

- Phone numbers should include country code (e.g., 2250707123456 for Côte d'Ivoire)
- Sender ID max length is 11 characters
- Messages are logged in development mode for testing
- OTP codes expire after 5 minutes (configurable in `config/prosartisan.php`)
