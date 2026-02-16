# Third-Party Integrations Setup Guide

This document outlines all third-party services required for the ProsArtisan platform and how to configure them.

## 📱 1. Firebase (Push Notifications & Cloud Messaging)

### Setup Steps:

1. **Create Firebase Project**
   - Go to: https://console.firebase.google.com
   - Click "Add project"
   - Name: `ProsArtisan` (or your preferred name)
   - Disable Google Analytics (optional)

2. **Add Android App**
   - Click "Add app" → Android icon
   - Package name: `com.prosartisan.app`
   - Download `google-services.json`
   - Place it in: `frontend/android/app/google-services.json`

3. **Add iOS App**
   - Click "Add app" → iOS icon
   - Bundle ID: `com.prosartisan.app`
   - Download `GoogleService-Info.plist`
   - Place it in: `frontend/ios/Runner/GoogleService-Info.plist`

4. **Enable Cloud Messaging**
   - In Firebase Console → Project Settings → Cloud Messaging
   - Note down the Server Key
   - Add to `backend/.env`:
     ```
     FIREBASE_SERVER_KEY=your_server_key_here
     FIREBASE_PROJECT_ID=your_project_id
     ```

5. **Configure Flutter**
   ```bash
   cd frontend
   flutter pub run firebase_core:configure
   ```

### Backend Configuration:
Add to `backend/.env`:
```env
FIREBASE_PROJECT_ID=prosartisan-xxxxx
FIREBASE_SERVER_KEY=AAAA...xxxxx
```

---

## 🗺️ 2. Google Maps API

### Setup Steps:

1. **Enable APIs**
   - Go to: https://console.cloud.google.com
   - Create new project or select existing
   - Enable the following APIs:
     - Maps SDK for Android
     - Maps SDK for iOS
     - Geocoding API
     - Places API
     - Geolocation API

2. **Create API Keys**
   - Go to: APIs & Services → Credentials
   - Create 3 API keys:
     - Android Maps Key (restrict to Android app)
     - iOS Maps Key (restrict to iOS app)
     - Backend Key (restrict to server IP)

3. **Configure Android**
   Edit `frontend/android/app/src/main/AndroidManifest.xml`:
   ```xml
   <application>
       <meta-data
           android:name="com.google.android.geo.API_KEY"
           android:value="YOUR_ANDROID_API_KEY"/>
   </application>
   ```

4. **Configure iOS**
   Edit `frontend/ios/Runner/AppDelegate.swift`:
   ```swift
   import GoogleMaps

   @UIApplicationMain
   @objc class AppDelegate: FlutterAppDelegate {
     override func application(
       _ application: UIApplication,
       didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
     ) -> Bool {
       GMSServices.provideAPIKey("YOUR_IOS_API_KEY")
       GeneratedPluginRegistrant.register(with: self)
       return super.application(application, didFinishLaunchingWithOptions: launchOptions)
     }
   }
   ```

### Backend Configuration:
Add to `backend/.env`:
```env
GOOGLE_MAPS_API_KEY=AIza...xxxxx
```

---

## 📲 3. SMS Provider (Africa's Talking - Recommended for Côte d'Ivoire)

### Setup Steps:

1. **Create Account**
   - Go to: https://africastalking.com
   - Sign up for an account
   - Complete verification

2. **Get Sandbox Credentials**
   - Go to Dashboard → Sandbox
   - Note down:
     - Username (usually: `sandbox`)
     - API Key

3. **Production Setup** (Later)
   - Add production application
   - Purchase SMS credits
   - Get production API key

### Backend Configuration:
Add to `backend/.env`:
```env
# SMS Provider (africastalking or twilio)
SMS_PROVIDER=africastalking
SMS_API_KEY=atsk_xxxxxxxxxxxxx
SMS_USERNAME=sandbox  # or your production username
```

### Alternative: Twilio
If using Twilio instead:
- Go to: https://www.twilio.com
- Get Account SID and Auth Token
```env
SMS_PROVIDER=twilio
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxx
TWILIO_PHONE_NUMBER=+225xxxxxxxxxx
```

---

## 💳 4. Mobile Money (CinetPay - Côte d'Ivoire)

### Setup Steps:

1. **Create Merchant Account**
   - Go to: https://cinetpay.com
   - Sign up as merchant
   - Complete KYC verification
   - Wait for approval (2-5 business days)

2. **Get API Credentials**
   - Login to merchant dashboard
   - Go to: Settings → API Keys
   - Note down:
     - API Key
     - Site ID
     - Secret Key

3. **Configure Webhook**
   - In CinetPay dashboard → Webhooks
   - Set notification URL to:
     ```
     https://your-domain.com/api/v1/payments/webhook
     ```
   - Enable notifications for:
     - Payment success
     - Payment failure
     - Payment pending

### Backend Configuration:
Add to `backend/.env`:
```env
# Mobile Money (CinetPay)
CINETPAY_API_KEY=xxxxxxxxxxxxx
CINETPAY_SITE_ID=xxxxx
CINETPAY_SECRET_KEY=xxxxxxxxxxxxx
CINETPAY_RETURN_URL=https://prosartisan.net/payment/callback
CINETPAY_NOTIFY_URL=https://prosartisan.net/api/v1/payments/webhook
```

### Supported Operators:
- Orange Money (Côte d'Ivoire)
- MTN Mobile Money
- Moov Money
- Wave (Senegal)
- Visa/Mastercard

### Test Mode:
CinetPay provides test credentials for development:
```env
CINETPAY_API_KEY=test_api_key
CINETPAY_SITE_ID=test_site_id
CINETPAY_SECRET_KEY=test_secret_key
```

---

## 🔒 5. Security Considerations

### Environment Variables
**NEVER commit the following to Git:**
- `.env` files
- `google-services.json`
- `GoogleService-Info.plist`
- API keys in code

### Gitignore
Ensure `backend/.env` and Firebase config files are in `.gitignore`:
```
backend/.env
frontend/android/app/google-services.json
frontend/ios/Runner/GoogleService-Info.plist
```

### Production Checklist
- [ ] Enable API key restrictions (IP, referrer, app)
- [ ] Set up billing alerts in Google Cloud
- [ ] Use production Firebase project (not default)
- [ ] Enable 2FA on all third-party accounts
- [ ] Rotate keys every 90 days
- [ ] Monitor API usage and quotas

---

## 📊 6. Cost Estimates (Monthly)

### Firebase (Free Tier)
- Cloud Messaging: Free (unlimited)
- Realtime Database: Free up to 1GB
- **Upgrade to Blaze Plan if scaling beyond 10K users**

### Google Maps API
- First $200/month: Free (Google Cloud credits)
- Maps SDK: $2 per 1,000 loads
- Geocoding: $5 per 1,000 requests
- **Estimated: $50-200/month for MVP**

### Africa's Talking (SMS)
- Côte d'Ivoire: ~$0.05 per SMS
- Bulk pricing available
- **Estimated: $100-300/month (2,000-6,000 SMS)**

### CinetPay (Mobile Money)
- Transaction fee: 1.5% - 3% (depending on volume)
- No monthly fees
- **Commission on transactions only**

### Total Estimated Monthly Cost (MVP)
- **Development/Testing**: $0-50
- **Production (1,000 users)**: $200-500
- **Production (10,000 users)**: $500-1,500

---

## ✅ Verification Checklist

### Backend
- [ ] All `.env` variables set
- [ ] SMS test successful: `php artisan tinker` → send test SMS
- [ ] CinetPay webhook responding (use test payment)
- [ ] Google Maps geocoding working

### Frontend
- [ ] Firebase initialized without errors
- [ ] Google Maps displays correctly
- [ ] Push notification permissions requested
- [ ] Test payment flow completes

### Run Tests
```bash
# Backend
cd backend
php artisan test

# Frontend
cd frontend
flutter test
```

---

## 🆘 Troubleshooting

### Firebase Not Working
- Check package names match (Android/iOS)
- Ensure `google-services.json` is in correct location
- Run `flutter clean && flutter pub get`

### Google Maps Not Displaying
- Verify API key is enabled
- Check billing is enabled in Google Cloud
- Ensure correct key for platform (Android vs iOS)

### SMS Not Sending
- Check account balance (Africa's Talking)
- Verify phone number format (+225XXXXXXXXXX)
- Check sandbox vs production mode

### CinetPay Payment Failing
- Verify test credentials
- Check webhook URL is accessible (HTTPS required)
- Review transaction logs in CinetPay dashboard

---

## 📞 Support Contacts

- **Firebase**: https://firebase.google.com/support
- **Google Maps**: https://cloud.google.com/support
- **Africa's Talking**: support@africastalking.com
- **CinetPay**: support@cinetpay.com

---

**Last Updated**: 2026-02-16
**Phase**: 0 - Infrastructure Foundation
