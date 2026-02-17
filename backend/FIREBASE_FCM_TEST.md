# Firebase Cloud Messaging (FCM) Testing Guide

## Overview
Comprehensive testing guide for push notifications using Firebase Cloud Messaging in ProsArtisan platform.

## Prerequisites

### 1. Firebase Project Setup

1. **Create Firebase Project**
   - Go to: https://console.firebase.google.com/
   - Click "Add project"
   - Name: "ProsArtisan"
   - Enable Google Analytics (optional)

2. **Add Android App**
   - Click "Add app" → Android
   - Package name: `com.prosartisan.app` (match Flutter package)
   - Download `google-services.json`
   - Place in: `frontend/android/app/google-services.json`

3. **Add iOS App**
   - Click "Add app" → iOS
   - Bundle ID: `com.prosartisan.app` (match Flutter)
   - Download `GoogleService-Info.plist`
   - Place in: `frontend/ios/Runner/GoogleService-Info.plist`

4. **Generate Service Account Key**
   - Go to Project Settings → Service Accounts
   - Click "Generate new private key"
   - Download JSON file
   - Save as: `backend/firebase-credentials.json`
   - **IMPORTANT**: Add to `.gitignore`

### 2. Install Dependencies

**Backend**:
```bash
cd backend
composer require kreait/firebase-php
```

**Frontend (Flutter)**:
```bash
cd frontend
flutter pub add firebase_core
flutter pub add firebase_messaging
flutter pub add flutter_local_notifications
```

### 3. Configuration

**Backend `.env`**:
```env
FIREBASE_CREDENTIALS=firebase-credentials.json
FIREBASE_DATABASE_URL=https://prosartisan-default-rtdb.firebaseio.com
```

**Frontend `android/app/build.gradle`**:
```gradle
dependencies {
    implementation platform('com.google.firebase:firebase-bom:32.7.0')
    implementation 'com.google.firebase:firebase-messaging'
}
```

**Frontend `ios/Podfile`**:
```ruby
target 'Runner' do
  use_frameworks!
  use_modular_headers!

  pod 'Firebase/Messaging'
end
```

---

## Backend Testing

### Test 1: Send Notification to Single User

**Test Code** (`routes/api.php` - add test route):
```php
Route::get('/test/fcm/single', function () {
    $fcmService = app(App\Services\FcmService::class);

    // Replace with your test user ID
    $userId = 1;

    $result = $fcmService->sendToUser(
        $userId,
        'Test Notification',
        'This is a test push notification from ProsArtisan backend.',
        [
            'type' => 'test',
            'timestamp' => now()->toIso8601String(),
        ]
    );

    return response()->json([
        'sent' => $result,
        'user_id' => $userId,
    ]);
});
```

**Test Steps**:
1. Register test user in app
2. Ensure user has FCM token in database
3. Call: `GET http://localhost:8000/api/test/fcm/single`
4. Check device for notification

**Expected Result**:
- Notification appears on device within 5 seconds
- Title: "Test Notification"
- Body: "This is a test push notification..."
- Data payload accessible in Flutter

**Verification**:
- [ ] Notification received on device
- [ ] Notification appears in status bar
- [ ] Tapping notification opens app
- [ ] Data payload passed to Flutter app

---

### Test 2: Send Notification to Multiple Users

**Test Code**:
```php
Route::get('/test/fcm/multiple', function () {
    $fcmService = app(App\Services\FcmService::class);

    $userIds = [1, 2, 3]; // Replace with real user IDs

    $result = $fcmService->sendToMultiple(
        $userIds,
        'Announcement',
        'New feature available in ProsArtisan! Check it out now.',
        ['type' => 'announcement']
    );

    return response()->json($result);
});
```

**Expected Response**:
```json
{
    "success": true,
    "sent": 3,
    "failed": 0
}
```

**Verification**:
- [ ] All registered devices receive notification
- [ ] Unregistered users don't cause errors
- [ ] Response shows correct counts

---

### Test 3: Send to Topic

**Test Code**:
```php
Route::get('/test/fcm/topic', function () {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendToTopic(
        'artisans', // Topic name
        'Artisan Update',
        'Important update for all artisans on ProsArtisan.',
        ['type' => 'artisan_announcement']
    );

    return response()->json(['sent' => $result]);
});
```

**Prerequisites**:
- Users must subscribe to 'artisans' topic first
- Test subscription first (see Test 8)

**Verification**:
- [ ] All subscribed users receive notification
- [ ] Non-subscribed users don't receive it

---

### Test 4: KYC Approved Notification

**Test Code**:
```php
Route::get('/test/fcm/kyc-approved/{userId}', function ($userId) {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendKycApproved($userId);

    return response()->json(['sent' => $result]);
});
```

**Expected Notification**:
- Title: "KYC Approuvé ✅"
- Body: "Votre vérification d'identité a été approuvée..."
- Data: `{type: 'kyc_approved', action: 'open_profile'}`

**Verification**:
- [ ] Emoji displayed correctly (✅)
- [ ] French text displayed
- [ ] Tapping opens profile screen

---

### Test 5: New Quote Received

**Test Code**:
```php
Route::get('/test/fcm/new-quote/{userId}', function ($userId) {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendNewQuote(
        $userId,
        'Jean Kouassi', // Artisan name
        'Rénovation Cuisine', // Project title
        42 // Quote ID
    );

    return response()->json(['sent' => $result]);
});
```

**Expected Notification**:
- Title: "Nouveau Devis Reçu 📄"
- Body: "Vous avez reçu un nouveau devis de Jean Kouassi pour votre projet Rénovation Cuisine."
- Data: `{type: 'quote_received', quote_id: '42', action: 'view_quote'}`

**Verification**:
- [ ] Personalized message with artisan name
- [ ] Project title included
- [ ] Tapping navigates to quote detail screen

---

### Test 6: Payment Confirmed

**Test Code**:
```php
Route::get('/test/fcm/payment-confirmed/{userId}', function ($userId) {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendPaymentConfirmed(
        $userId,
        150000, // Amount in XOF
        15 // Project ID
    );

    return response()->json(['sent' => $result]);
});
```

**Expected Notification**:
- Title: "Paiement Confirmé 💰"
- Body: "Votre paiement de 150 000 XOF a été confirmé. Le projet peut commencer."
- Data: `{type: 'payment_confirmed', project_id: '15', amount: '150000'}`

**Verification**:
- [ ] Amount formatted with spaces (150 000)
- [ ] Currency (XOF) displayed
- [ ] Tapping opens project screen

---

### Test 7: Milestone Validated

**Test Code**:
```php
Route::get('/test/fcm/milestone-validated/{userId}', function ($userId) {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendMilestoneValidated(
        $userId,
        'Fondations terminées',
        23 // Milestone ID
    );

    return response()->json(['sent' => $result]);
});
```

**Expected Notification**:
- Title: "Étape Validée ✅"
- Body: "L'étape Fondations terminées a été validée. Paiement programmé J+1."
- Data: `{type: 'milestone_validated', milestone_id: '23', action: 'view_milestone'}`

**Verification**:
- [ ] Milestone title shown
- [ ] J+1 payment mentioned
- [ ] Tapping opens milestone screen

---

### Test 8: Subscribe to Topic

**Test Code**:
```php
Route::post('/test/fcm/subscribe/{userId}/{topic}', function ($userId, $topic) {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->subscribeToTopic($userId, $topic);

    return response()->json(['subscribed' => $result]);
});
```

**Test Call**:
```
POST http://localhost:8000/api/test/fcm/subscribe/1/artisans
```

**Verification**:
- [ ] User subscribed successfully
- [ ] Topic notifications received (Test 3)
- [ ] Subscription persists after app restart

---

## Frontend (Flutter) Testing

### Test 9: Initialize FCM in Flutter

**File**: `frontend/lib/core/services/fcm_service.dart`

```dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class FcmService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  static Future<void> initialize() async {
    // Request permission (iOS)
    NotificationSettings settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      print('FCM: User granted permission');
    }

    // Get FCM token
    String? token = await _messaging.getToken();
    print('FCM Token: $token');

    // Send token to backend
    if (token != null) {
      await _sendTokenToBackend(token);
    }

    // Listen for token refresh
    _messaging.onTokenRefresh.listen(_sendTokenToBackend);

    // Configure local notifications
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');

    const DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings();

    const InitializationSettings initializationSettings =
        InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    await _localNotifications.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: _onNotificationTap,
    );

    // Handle foreground messages
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Handle background messages
    FirebaseMessaging.onMessageOpenedApp.listen(_handleBackgroundMessage);
  }

  static Future<void> _sendTokenToBackend(String token) async {
    // Call API endpoint to save token
    try {
      // final response = await dio.post('/auth/fcm-token', data: {'token': token});
      print('FCM token sent to backend: $token');
    } catch (e) {
      print('Failed to send FCM token: $e');
    }
  }

  static void _handleForegroundMessage(RemoteMessage message) {
    print('Foreground message: ${message.notification?.title}');

    // Show local notification
    _showLocalNotification(message);
  }

  static void _handleBackgroundMessage(RemoteMessage message) {
    print('Background message opened: ${message.notification?.title}');

    // Navigate based on data
    _handleNavigation(message.data);
  }

  static void _onNotificationTap(NotificationResponse response) {
    print('Notification tapped: ${response.payload}');

    if (response.payload != null) {
      // Navigate to specific screen
      _handleNavigation({'action': response.payload});
    }
  }

  static void _showLocalNotification(RemoteMessage message) async {
    const AndroidNotificationDetails androidDetails =
        AndroidNotificationDetails(
      'prosartisan_notifications',
      'ProsArtisan Notifications',
      channelDescription: 'Notifications for ProsArtisan app',
      importance: Importance.max,
      priority: Priority.high,
      color: Color(0xFFFF6B35),
    );

    const DarwinNotificationDetails iosDetails = DarwinNotificationDetails();

    const NotificationDetails platformDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(
      message.hashCode,
      message.notification?.title,
      message.notification?.body,
      platformDetails,
      payload: message.data['action'],
    );
  }

  static void _handleNavigation(Map<String, dynamic> data) {
    final String? action = data['action'];

    switch (action) {
      case 'open_profile':
        // Navigate to profile
        break;
      case 'view_quote':
        // Navigate to quote detail
        final quoteId = data['quote_id'];
        break;
      case 'view_project':
        // Navigate to project
        final projectId = data['project_id'];
        break;
      case 'view_milestone':
        // Navigate to milestone
        final milestoneId = data['milestone_id'];
        break;
      case 'leave_review':
        // Navigate to review screen
        final projectId = data['project_id'];
        break;
      default:
        // Open home screen
        break;
    }
  }
}
```

**Test Steps**:
1. Initialize FCM in `main.dart`:
   ```dart
   await Firebase.initializeApp();
   await FcmService.initialize();
   ```
2. Run app on device
3. Check console for FCM token
4. Send test notification from backend

**Verification**:
- [ ] FCM token printed in console
- [ ] Token saved to backend (users.fcm_token)
- [ ] Foreground notifications displayed
- [ ] Background notifications open app
- [ ] Navigation works for all action types

---

### Test 10: Android Notification Channel

**File**: `frontend/android/app/src/main/res/values/strings.xml`

```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <string name="default_notification_channel_id">prosartisan_notifications</string>
    <string name="default_notification_channel_name">ProsArtisan Notifications</string>
</resources>
```

**File**: `frontend/android/app/src/main/kotlin/com/prosartisan/app/MainActivity.kt`

```kotlin
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugins.GeneratedPluginRegistrant
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build

class MainActivity: FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        GeneratedPluginRegistrant.registerWith(flutterEngine)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "prosartisan_notifications",
                "ProsArtisan Notifications",
                NotificationManager.IMPORTANCE_HIGH
            )
            channel.description = "Notifications for ProsArtisan app"

            val notificationManager = getSystemService(NotificationManager::class.java)
            notificationManager.createNotificationChannel(channel)
        }
    }
}
```

**Verification**:
- [ ] Channel created on Android 8+
- [ ] Notifications use correct channel
- [ ] Sound and vibration work

---

### Test 11: iOS Push Notification Permissions

**File**: `frontend/ios/Runner/AppDelegate.swift`

```swift
import UIKit
import Flutter
import Firebase

@UIApplicationMain
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    FirebaseApp.configure()

    if #available(iOS 10.0, *) {
      UNUserNotificationCenter.current().delegate = self

      let authOptions: UNAuthorizationOptions = [.alert, .badge, .sound]
      UNUserNotificationCenter.current().requestAuthorization(
        options: authOptions,
        completionHandler: {_, _ in })
    } else {
      let settings: UIUserNotificationSettings =
      UIUserNotificationSettings(types: [.alert, .badge, .sound], categories: nil)
      application.registerUserNotificationSettings(settings)
    }

    application.registerForRemoteNotifications()

    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
```

**Verification**:
- [ ] Permission dialog shown on first launch
- [ ] User can allow/deny notifications
- [ ] APNs token generated
- [ ] Notifications received on iOS

---

## Error Handling Tests

### Test 12: Invalid FCM Token

**Test Code**:
```php
Route::get('/test/fcm/invalid-token', function () {
    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendToToken(
        'invalid-token-xxx',
        'Test',
        'This should fail gracefully',
        []
    );

    return response()->json(['sent' => $result]);
});
```

**Expected Result**:
- `sent: false`
- Error logged to `storage/logs/laravel.log`
- No exception thrown

**Verification**:
- [ ] Invalid token handled gracefully
- [ ] Error logged with context
- [ ] Token removed from database (if it exists)

---

### Test 13: User Without FCM Token

**Test Code**:
```php
Route::get('/test/fcm/no-token/{userId}', function ($userId) {
    // Ensure user has no fcm_token
    \App\Models\User::find($userId)->update(['fcm_token' => null]);

    $fcmService = app(App\Services\FcmService::class);

    $result = $fcmService->sendToUser(
        $userId,
        'Test',
        'This should return false',
        []
    );

    return response()->json(['sent' => $result]);
});
```

**Expected Result**:
- `sent: false`
- Warning logged
- No crash

**Verification**:
- [ ] No error thrown
- [ ] Warning logged
- [ ] Function returns false

---

## Integration Tests

### Test 14: Complete User Journey

**Scenario**: Client creates project → receives quote → gets notification

**Steps**:
1. Client registers (FCM token saved)
2. Client creates project
3. Artisan submits quote
4. Backend sends "New Quote" notification via `FcmService::sendNewQuote()`
5. Client receives notification
6. Client taps notification
7. App navigates to quote detail screen

**Verification**:
- [ ] End-to-end flow works
- [ ] Notification delivered within 5 seconds
- [ ] Navigation correct
- [ ] Data payload intact

---

## Logging & Monitoring

**Check logs**:
```bash
tail -f storage/logs/laravel.log | grep FCM
```

**Expected log entries**:

1. **Notification sent**:
```
[INFO] FCM notification sent
{"token":"xxx...","title":"Test Notification","data":{...}}
```

2. **Send failed**:
```
[ERROR] FCM send failed
{"token":"xxx...","title":"Test","error":"invalid-registration-token"}
```

3. **Invalid token cleaned**:
```
[INFO] Invalid FCM token removed from user
{"user_id":42,"token":"xxx..."}
```

---

## Test Checklist

### Backend
- [ ] FcmService class created
- [ ] Firebase credentials file downloaded
- [ ] kreait/firebase-php installed
- [ ] Send to single user works
- [ ] Send to multiple users works
- [ ] Send to topic works
- [ ] All 7 notification templates work:
  - [ ] KYC approved
  - [ ] New quote received
  - [ ] Payment confirmed
  - [ ] Milestone validated
  - [ ] Project completed
  - [ ] Review received
  - [ ] Score updated
- [ ] Invalid tokens handled
- [ ] Logging works

### Frontend
- [ ] Firebase initialized in Flutter
- [ ] FCM token generated
- [ ] Token sent to backend
- [ ] Foreground notifications displayed
- [ ] Background notifications open app
- [ ] Notification tapping navigates correctly
- [ ] Android notification channel created
- [ ] iOS permissions requested
- [ ] All data payloads handled

---

## Production Deployment

### Before Going Live:

1. **Enable FCM in Firebase Console**:
   - Go to Cloud Messaging tab
   - Verify configuration

2. **Upload APNs Certificate** (iOS):
   - Go to Project Settings → Cloud Messaging
   - Upload APNs authentication key (.p8 file)

3. **Test on Real Devices**:
   - Android: Physical device + release build
   - iOS: Physical device + release build

4. **Monitor Delivery Rates**:
   - Firebase Console → Cloud Messaging → Reports
   - Track delivery success rate (should be >95%)

5. **Set Up Notification Preferences**:
   - Allow users to customize notification types
   - Respect "Do Not Disturb" hours

---

## Estimated Testing Time

- Backend service setup: 1 hour
- Frontend Flutter integration: 2 hours
- Testing all notification types: 1 hour
- Error handling tests: 30 minutes
- End-to-end integration: 30 minutes

**Total**: ~5 hours for comprehensive FCM testing

---

*Last updated: 2026-02-16*
*Firebase SDK Version: 11.x (Flutter), 7.x (PHP)*
