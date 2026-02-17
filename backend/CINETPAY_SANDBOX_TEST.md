# CinetPay Sandbox Testing Guide

## Overview
This guide provides step-by-step instructions for testing CinetPay Mobile Money integration in sandbox mode for ProsArtisan platform.

## Prerequisites

1. **CinetPay Sandbox Account**
   - Sign up at: https://cinetpay.com/
   - Get sandbox credentials from your dashboard
   - Required credentials:
     - API Key
     - Site ID
     - Secret Key

2. **Update .env file** with sandbox credentials:
```env
CINETPAY_API_KEY=your_sandbox_api_key_here
CINETPAY_SITE_ID=your_sandbox_site_id_here
CINETPAY_SECRET_KEY=your_sandbox_secret_key_here
CINETPAY_MODE=sandbox
CINETPAY_RETURN_URL=${APP_URL}/payment/callback
CINETPAY_NOTIFY_URL=${APP_URL}/api/v1/payments/webhook
```

## Test Scenarios

### 1. Payment Initialization (Wave)

**Endpoint**: `POST /api/v1/payments/initialize`

**Request**:
```json
{
    "project_id": 1,
    "payment_method": "WALLET"
}
```

**Expected Response**:
```json
{
    "transaction_ref": "PRO-1-1708123456",
    "payment_url": "https://checkout.cinetpay.com/payment/...",
    "payment_token": "xxxxx"
}
```

**Test Steps**:
1. Make API request with valid project_id
2. Verify response contains payment_url
3. Open payment_url in browser
4. Select Wave wallet
5. Complete payment with sandbox credentials
6. Verify redirect to return_url

**Verification**:
- [ ] Transaction created in database with status 'pending'
- [ ] Payment URL generated
- [ ] User redirected to CinetPay checkout page
- [ ] Payment methods displayed: Wave, Orange Money, MTN, Moov

---

### 2. Payment Initialization (Orange Money)

**Request**:
```json
{
    "project_id": 1,
    "payment_method": "MOBILE_MONEY"
}
```

**Test Amount**: 5000 XOF

**Steps**:
1. Initialize payment
2. Select Orange Money on checkout page
3. Enter sandbox phone number: +225 XX XX XX XX XX
4. Enter sandbox OTP code (provided by CinetPay docs)
5. Confirm payment

**Verification**:
- [ ] Payment completes successfully
- [ ] Webhook called with status '00' (success)
- [ ] Transaction status updated to 'completed'

---

### 3. Payment Initialization (MTN Mobile Money)

**Request**:
```json
{
    "project_id": 1,
    "payment_method": "MOBILE_MONEY"
}
```

**Test Amount**: 10000 XOF

**Steps**:
1. Initialize payment
2. Select MTN Mobile Money
3. Enter sandbox MTN number
4. Complete payment with sandbox PIN

**Verification**:
- [ ] Payment completes
- [ ] Escrow wallet created with correct fragmentation
- [ ] Material token generated

---

### 4. Webhook Handling (Success)

**Endpoint**: `POST /api/v1/payments/webhook`

**Test Payload** (simulated from CinetPay):
```json
{
    "cpm_site_id": "your_site_id",
    "cpm_trans_id": "TRX123456",
    "cpm_custom": "PRO-1-1708123456",
    "cpm_amount": "10000",
    "cpm_currency": "XOF",
    "cpm_payid": "PAY123",
    "payment_method": "WAVE",
    "operator_id": "OP123",
    "cpm_phone_number": "+2250123456789",
    "cel_phone_num": "+2250123456789",
    "cpm_ipn_ack": "1",
    "created_at": "2026-02-16 10:00:00",
    "updated_at": "2026-02-16 10:05:00",
    "cpm_result": "00",
    "cpm_trans_status": "ACCEPTED",
    "cpm_designation": "Paiement projet #1",
    "signature": "computed_signature_here"
}
```

**Test Steps**:
1. Compute HMAC signature:
   ```php
   $signature = hash_hmac('sha256', json_encode($payload), $secretKey);
   ```
2. Send POST request with header: `X-CinetPay-Signature: $signature`
3. Verify webhook processing

**Verification**:
- [ ] Signature verified successfully
- [ ] Transaction status updated to 'completed'
- [ ] Escrow wallet created
- [ ] Material wallet = total_amount * 70%
- [ ] Labor wallet = total_amount * 30%
- [ ] Material token generated with QR code
- [ ] Project status updated to 'payment_pending'

---

### 5. Webhook Handling (Failed Payment)

**Test Payload**:
```json
{
    "cpm_result": "01",
    "cpm_trans_status": "FAILED",
    ...
}
```

**Verification**:
- [ ] Transaction status updated to 'failed'
- [ ] No escrow wallet created
- [ ] Project status unchanged

---

### 6. Payment Verification

**Endpoint**: `GET /api/v1/payments/verify/{transactionRef}`

**Example**: `GET /api/v1/payments/verify/PRO-1-1708123456`

**Expected Response**:
```json
{
    "status": "completed",
    "payment_status": "ACCEPTED",
    "amount": 10000
}
```

**Verification**:
- [ ] API returns correct transaction status
- [ ] CinetPay API called for verification
- [ ] Payment status matches webhook data

---

### 7. Escrow Wallet Creation

**After successful payment:**

**Check database**:
```sql
SELECT * FROM escrow_wallets WHERE project_id = 1;
```

**Expected**:
- `total_amount`: 10000
- `material_wallet`: 7000 (70%)
- `labor_wallet`: 3000 (30%)
- `material_spent`: 0
- `labor_released`: 0
- `status`: 'active'

**Verification**:
- [ ] Wallet created with correct fragmentation
- [ ] Percentages match accepted quote
- [ ] Status set to 'active'

---

### 8. Material Token Generation

**After escrow wallet created:**

**Check database**:
```sql
SELECT * FROM material_tokens WHERE project_id = 1;
```

**Expected**:
- `code`: PA-XXXXXX (6 characters)
- `total_value`: 7000 (material wallet amount)
- `remaining_value`: 7000
- `qr_code_path`: tokens/PA-XXXXXX.png
- `expires_at`: 30 days from now
- `status`: 'active'

**Verification**:
- [ ] Token code is unique
- [ ] QR code file exists in storage/app/public/tokens/
- [ ] QR code contains correct data (code, amount, project_id, expiry)
- [ ] QR code is scannable

---

### 9. Error Handling - Network Timeout

**Test**: Simulate network timeout

**Steps**:
1. Temporarily modify CinetPayService timeout to 1ms
2. Attempt payment initialization
3. Verify error handling

**Expected**:
- Error logged to storage/logs/payments.log
- Transaction marked as 'failed'
- User receives error message: "Payment initialization failed"

**Verification**:
- [ ] Error logged with context
- [ ] Transaction status is 'failed'
- [ ] No escrow wallet created

---

### 10. Error Handling - Invalid Signature

**Test**: Send webhook with invalid signature

**Steps**:
1. Send webhook POST with wrong signature header
2. Verify rejection

**Expected Response**:
```json
{
    "error": "Invalid signature"
}
```
**HTTP Status**: 401 Unauthorized

**Verification**:
- [ ] Request rejected
- [ ] No database changes
- [ ] Security violation logged

---

## Logging Verification

**Check payment logs**:
```bash
tail -f storage/logs/payments.log
```

**Expected log entries**:
1. Payment initiated:
   ```
   [timestamp] local.INFO: CinetPay payment initiated
   {"transaction_id":"PRO-1-xxx","amount":10000,"project_id":1}
   ```

2. Webhook processed:
   ```
   [timestamp] local.INFO: Webhook processed successfully
   {"transaction_id":"TRX123","status":"00","amount":"10000"}
   ```

3. Escrow wallet created:
   ```
   [timestamp] local.INFO: Escrow wallet created
   {"project_id":1,"escrow_wallet_id":1,"total_amount":10000}
   ```

**Verification**:
- [ ] All payment actions logged
- [ ] Logs include relevant context
- [ ] No sensitive data (API keys) logged

---

## Integration Test Checklist

### Prerequisites
- [ ] CinetPay sandbox account created
- [ ] Sandbox credentials added to .env
- [ ] Database seeded with test data (users, projects, quotes)
- [ ] Sanctum authentication configured
- [ ] QR code package installed (simplesoftwareio/simple-qrcode)

### Test Sequence
- [ ] 1. Initialize payment (Wave) - 1000 XOF
- [ ] 2. Complete payment on CinetPay checkout
- [ ] 3. Verify webhook called with success status
- [ ] 4. Check transaction status in database
- [ ] 5. Verify escrow wallet created
- [ ] 6. Verify material token generated
- [ ] 7. Verify QR code file exists
- [ ] 8. Test payment verification endpoint
- [ ] 9. Initialize payment (Orange Money) - 5000 XOF
- [ ] 10. Initialize payment (MTN) - 10000 XOF
- [ ] 11. Test failed payment scenario
- [ ] 12. Test invalid signature webhook
- [ ] 13. Review payment logs

### Success Criteria
- [ ] All 3 payment methods work (Wave, Orange, MTN)
- [ ] Webhook signature validation works
- [ ] Escrow fragmentation correct (70/30)
- [ ] Material tokens generated with QR codes
- [ ] All payments logged correctly
- [ ] Error handling works for all failure scenarios

---

## Sandbox Test Data (from CinetPay Docs)

**Test Phone Numbers** (check CinetPay sandbox docs):
- Wave: +225 XX XX XX XX XX
- Orange Money: +225 XX XX XX XX XX
- MTN: +225 XX XX XX XX XX

**Test OTP Codes**:
- Usually: `123456` or `000000` (check sandbox docs)

**Test Amounts**:
- Minimum: 100 XOF
- Maximum: 1,000,000 XOF (sandbox limit may vary)

---

## Common Issues & Troubleshooting

### Issue: Payment URL not generated
**Cause**: Invalid API credentials
**Fix**: Double-check .env credentials match CinetPay dashboard

### Issue: Webhook not called
**Cause**: notify_url not publicly accessible
**Fix**:
- Use ngrok for local testing: `ngrok http 8000`
- Update CINETPAY_NOTIFY_URL in .env to ngrok URL

### Issue: Signature validation fails
**Cause**: Secret key mismatch
**Fix**: Ensure secret_key in .env matches CinetPay dashboard exactly

### Issue: QR code not generated
**Cause**: Storage permissions
**Fix**: Run `php artisan storage:link` and check permissions

---

## Next Steps After Sandbox Testing

1. **Production Setup**:
   - Get production credentials from CinetPay
   - Update .env with production values
   - Set `CINETPAY_MODE=production`

2. **Security Hardening**:
   - Enable HTTPS for webhook URL
   - Add rate limiting to payment endpoints
   - Implement fraud detection rules

3. **Monitoring**:
   - Set up Sentry for error tracking
   - Configure payment alerts for failures
   - Daily reconciliation reports

---

## Estimated Testing Time

- Payment initialization (all 3 methods): 1 hour
- Webhook testing: 1 hour
- Escrow & token verification: 30 minutes
- Error handling tests: 30 minutes
- Documentation review: 1 hour

**Total**: ~4 hours for comprehensive sandbox testing

---

*Last updated: 2026-02-16*
*CinetPay API Version: v2*
