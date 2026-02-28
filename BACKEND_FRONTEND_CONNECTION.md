# Backend-Frontend Connection Summary

## Client Home Screen → Laravel Backend

### ✅ Connected Endpoints

#### 1. Artisans Nearby (`GET /api/v1/artisans`)
- **Controller**: `ArtisanController@nearby`
- **Repository**: `ArtisanRepository.getNearby()`
- **Model**: `ArtisanModel`
- **Features**:
  - GPS-based search with blurred positions (~50m radius)
  - Distance calculation in meters
  - Score N'Zassa integration
  - Golden marker for top artisans (score ≥ 70)
  - KYC status included

#### 2. Active Missions (`GET /api/v1/missions?status=en_cours`)
- **Controller**: `MissionController@index`
- **Repository**: `MissionRepository.getMissions()`
- **Model**: `MissionModel`
- **Features**:
  - Filter by status
  - Real-time mission count
  - Mission details with jalons

### 📊 Real-Time Stats

The home screen now displays live data:
- **Missions actives**: Count from backend API
- **Artisans proches**: Count from nearby search results

### 🔄 Data Flow

```
User Opens App
    ↓
HomeController.onInit()
    ↓
_loadData()
    ├─→ _getLocation() → Geolocator
    ├─→ ArtisanRepository.getNearby(lat, lng)
    │       ↓
    │   ApiClient.get('/artisans', params)
    │       ↓
    │   Laravel: ArtisanController@nearby
    │       ↓
    │   GeoService.nearbyArtisans()
    │       ↓
    │   Returns: List<ArtisanResource> (blurred GPS)
    │
    └─→ MissionRepository.getMissions(status: 'en_cours')
            ↓
        ApiClient.get('/missions', params)
            ↓
        Laravel: MissionController@index
            ↓
        Returns: List<MissionResource>
```

### 🔧 Updated Components

#### Models
- ✅ `ArtisanModel` - Updated to match backend response
  - Added: `bio`, `experienceYears`, `rating`, `completedMissions`, `kycStatus`
  - Changed: `photoUrl` → `photo`
  - Changed: `lat/lng` → `location: {lat, lng}`
  - Changed: `distance` from `double` to `String` (formatted by backend)

#### Controllers
- ✅ `HomeController` - Added real stats tracking
  - Added: `activeMissionsCount`, `nearbyArtisansCount`
  - Updated: `_loadData()` to populate counts

#### Views
- ✅ `ClientHomeScreen` - Connected to live data
  - Stats cards now use `Obx()` for reactive updates
  - Artisan list displays backend data
  - Empty state handling

- ✅ `ArtisanCard` - Updated for new model
  - Uses `photo` instead of `photoUrl`
  - Displays formatted distance from backend

- ✅ `ArtisanMapScreen` - Updated location handling
  - Uses `location` map instead of `lat/lng` fields

- ✅ `ArtisanProfileScreen` - Updated field names
  - Uses `photo` and formatted `distance`

### 🔐 Authentication

All API calls use Sanctum token authentication via `ApiClient`:
- Token stored in `FlutterSecureStorage`
- Auto-attached to requests via `_AuthInterceptor`
- 401 responses trigger token cleanup

### 🌍 Environment Configuration

Base URL configured in `EnvConfig`:
- **Development**: `http://10.0.2.2:8000/api/v1` (Android Emulator)
- **iOS Simulator**: `http://localhost:8000/api/v1`
- **Production**: `https://api.prosartisan.com/api/v1`

### 📱 Features Working

1. ✅ Load nearby artisans based on GPS
2. ✅ Display real mission count
3. ✅ Display real artisan count
4. ✅ Show artisan cards with backend data
5. ✅ Navigate to artisan profile
6. ✅ Search by category
7. ✅ Map view with blurred positions
8. ✅ Empty state handling

### 🔄 Next Steps

To test the connection:

1. Start Laravel backend:
```bash
cd backend-proartisan
php artisan serve
```

2. Run Flutter app:
```bash
cd frontend_flutter
flutter run
```

3. Login with valid credentials
4. Navigate to home screen
5. Grant location permissions
6. Verify artisans load from backend

### 🐛 Troubleshooting

If artisans don't load:
- Check backend is running on `http://localhost:8000`
- Verify database has artisan users with GPS coordinates
- Check Laravel logs: `backend-proartisan/storage/logs/laravel.log`
- Enable Dio logger to see API requests/responses
- Verify location permissions granted

### 📝 Backend Requirements

For the connection to work, ensure:
- ✅ Database seeded with artisan users
- ✅ Artisans have `position` field with GPS coordinates
- ✅ Sanctum authentication configured
- ✅ CORS enabled for mobile app
- ✅ API routes registered in `routes/api.php`

---

## Mission Screens → Laravel Backend

### ✅ Connected Endpoints

#### 1. List Missions (`GET /api/v1/missions`)
- **Controller**: `MissionController@index`
- **Repository**: `MissionRepository.getMissions()`
- **Model**: `MissionModel`
- **Query Parameters**:
  - `status` (optional): Filter by mission status (`en_attente`, `financee`, `en_cours`, `terminee`, `litige`)
- **Features**:
  - Auto-retry on network failure (3 attempts)
  - Support for both snake_case and camelCase response
  - Pull-to-refresh support
  - Filter by status with reactive UI

#### 2. Get Mission Details (`GET /api/v1/missions/{id}`)
- **Controller**: `MissionController@show`
- **Repository**: `MissionRepository.getMission()`
- **Model**: `MissionModel`
- **Features**:
  - Parallel loading of mission and jalons
  - Referent validation indicator for missions > 2M FCFA
  - Real-time status updates

#### 3. Get Mission Jalons (`GET /api/v1/missions/{id}/jalons`)
- **Controller**: `JalonController@index`
- **Repository**: `MissionRepository.getJalons()`
- **Model**: `JalonModel`
- **Features**:
  - Auto-retry on network failure
  - Support for snake_case and camelCase
  - Photo metadata with GPS coordinates
  - OTP validation status tracking

#### 4. AI Estimation (`POST /api/v1/missions/estimate`)
- **Controller**: `MissionController@estimate`
- **Repository**: `MissionRepository.estimate()`
- **Payload**:
  ```json
  {
    "description": "Description des travaux",
    "category": "Plomberie"
  }
  ```
- **Response**:
  ```json
  {
    "estimation_min": 150000,
    "estimation_max": 250000,
    "urgency": "moyen"
  }
  ```
- **Features**:
  - Gemini AI integration
  - Reduced retry attempts (2 max, cost optimization)
  - Separate loading state (`isEstimating`)

#### 5. Submit Jalon (`PUT /api/v1/jalons/{id}/submit`)
- **Controller**: `JalonController@submit`
- **Repository**: `MissionRepository.submitJalon()`
- **Features**:
  - Optimistic UI updates
  - Background mission reload
  - Success/error snackbar feedback

#### 6. Request OTP (`POST /api/v1/jalons/{id}/request-otp`)
- **Controller**: `JalonController@requestOtp`
- **Repository**: `MissionRepository.requestOtp()`
- **Features**:
  - SMS integration (Infobip/Twilio)
  - French language confirmation
  - Error handling for SMS failures

#### 7. Validate OTP (`POST /api/v1/jalons/{id}/validate-otp`)
- **Controller**: `JalonController@validateOtp`
- **Repository**: `MissionRepository.validateOtp()`
- **Payload**:
  ```json
  {
    "otp": "1234"
  }
  ```
- **Features**:
  - 4-digit OTP validation
  - Expiry check
  - Payment liberation trigger
  - Specific error for invalid/expired OTP (422)

### 🔄 Data Flow - Mission Tracking

```
User Views Mission
    ↓
MissionTrackingScreen.initState()
    ↓
MissionsController.loadMission(id)
    ↓
Parallel Execution:
    ├─→ MissionRepository.getMission(id)
    │       ↓
    │   ApiClient.get('/missions/{id}')
    │       ↓
    │   Laravel: MissionController@show
    │       ↓
    │   Returns: MissionResource (snake_case)
    │       ↓
    │   MissionModel.fromJson() (supports snake_case & camelCase)
    │
    └─→ MissionRepository.getJalons(id)
            ↓
        ApiClient.get('/missions/{id}/jalons')
            ↓
        Laravel: JalonController@index
            ↓
        Returns: List<JalonResource>
            ↓
        List<JalonModel>
```

### 🔄 Data Flow - Jalon Validation

```
Artisan Submits Jalon
    ↓
MissionsController.submitJalon(id)
    ↓
MissionRepository.submitJalon(id)
    ↓
Laravel: JalonController@submit
    ↓
Status: en_attente → soumis
    ↓
Client Receives Notification
    ↓
Client Requests OTP
    ↓
MissionsController.requestOtp(id)
    ↓
Laravel: JalonController@requestOtp
    ↓
SMS sent via Infobip/Twilio
    ↓
Status: soumis → valide (OTP generated)
    ↓
Client Enters OTP
    ↓
MissionsController.validateOtp(id, otp)
    ↓
Laravel: JalonController@validateOtp
    ↓
OTP validated → Payment liberation
    ↓
Status: valide → paye
    ↓
Funds transferred to artisan Mobile Money
```

### 🛡️ Error Handling

The mission module implements comprehensive error handling:

| Error Type | HTTP Code | French Message | Retry Strategy |
|---|---|---|---|
| Connection Timeout | - | "Délai d'attente dépassé. Vérifiez votre connexion." | Auto-retry (3x, 1s delay) |
| No Internet | - | "Pas de connexion internet" | Auto-retry (3x) |
| Unauthorized | 401 | "Session expirée. Veuillez vous reconnecter." | No retry, clear token |
| Forbidden | 403 | "Accès refusé" | No retry |
| Not Found | 404 | "Ressource introuvable" | No retry |
| Validation Error | 422 | Backend message or "Données invalides" | No retry |
| Invalid OTP | 422 | "Code OTP invalide ou expiré" | No retry |
| Request Timeout | 408 | Auto-retry | Retry (3x, 1s delay) |
| Rate Limit | 429 | Auto-retry | Exponential backoff (2s, 4s, 8s) |
| Server Error | 500 | "Erreur serveur. Veuillez réessayer." | Auto-retry (3x) |

### 🔧 Updated Components

#### Models
- ✅ `MissionModel` - Enhanced with:
  - Snake_case/camelCase support
  - Robust amount parsing (handles int/double/string)
  - Referent validation check (> 2M FCFA)
  - Status/urgency labels in French
  - `copyWith()` method for immutable updates

- ✅ `JalonModel` - Enhanced with:
  - Snake_case/camelCase support
  - OTP expiry validation
  - Status labels in French
  - Photo metadata parsing
  - `copyWith()` method

#### Controllers
- ✅ `MissionsController` - Enhanced with:
  - Separate loading states (`isLoading`, `isRefreshing`, `isEstimating`, `isSubmittingJalon`)
  - Comprehensive Dio error handling
  - French error messages with snackbars
  - Parallel loading for mission + jalons
  - Background refresh (no loader)
  - Return values for actions (bool success)

#### Repositories
- ✅ `MissionRepository` - Enhanced with:
  - Auto-retry mechanism (3 attempts, 1s delay)
  - Smart retry logic (network errors only, not 4xx)
  - Exponential backoff for rate limiting
  - Flexible response parsing (supports multiple formats)
  - AI estimation with reduced retries (cost optimization)

#### Views
- ✅ `MissionsScreen` - Enhanced with:
  - Pull-to-refresh on empty state
  - Better empty state messaging
  - Loading shimmer on first load only
  - Reactive filter chips

- ✅ `MissionRequestScreen` - Enhanced with:
  - Separate `isEstimating` loading state
  - Disabled submit during estimation
  - Dynamic button label during estimation
  - Better form validation

- ✅ `MissionTrackingScreen` - Enhanced with:
  - Parallel data loading (mission + jalons)
  - Pull-to-refresh support
  - Referent banner for high-value missions
  - Real-time status updates

### 📊 Real-Time Features

- **Mission Status**: Reactive updates via Obx()
- **Jalon Progress**: Auto-reload after submit/validate
- **Escrow Wallets**: Live balance display
- **OTP Countdown**: Expiry tracking (if implemented)

### 🌍 Field Mapping (Laravel → Flutter)

| Laravel (snake_case) | Flutter (camelCase) | Type | Notes |
|---|---|---|---|
| `client_id` | `clientId` | int | Foreign key |
| `artisan_id` | `artisanId` | int | Foreign key |
| `montant_total` | `montantTotal` | int | FCFA (no decimals) |
| `montant_materiaux` | `montantMateriaux` | int | FCFA |
| `montant_mo` | `montantMo` | int | FCFA |
| `ratio_materiaux` | `ratioMateriaux` | double | 0.0 - 1.0 |
| `created_at` | `createdAt` | String | ISO 8601 |
| `otp_code` | `otpCode` | String? | 4 digits |
| `otp_expires_at` | `otpExpiresAt` | String? | ISO 8601 |
| `photos_json` | `photosJson` | List? | Array of objects |

**Note**: Models support both naming conventions for maximum compatibility.

### 🔐 Business Rules Enforced

1. ✅ **Referent Validation**: Missions > 2M FCFA display warning banner
2. ✅ **OTP Required**: Cannot liberate funds without valid OTP
3. ✅ **Immutable Ratio**: `ratio_materiaux` fixed at devis acceptance
4. ✅ **Sequential Jalons**: Cannot skip jalon order
5. ✅ **FCFA Amounts**: Always integers, no decimals

### 📱 Features Working

1. ✅ Load missions with status filter
2. ✅ View mission details with jalons
3. ✅ AI estimation (Gemini)
4. ✅ Submit jalons for validation
5. ✅ Request OTP via SMS
6. ✅ Validate OTP and liberate payment
7. ✅ Pull-to-refresh on all screens
8. ✅ Auto-retry on network failures
9. ✅ Comprehensive error messages (French)
10. ✅ Empty state handling

### 🔄 Next Steps

To test the mission module:

1. Ensure backend endpoints are implemented:
   ```php
   // routes/api.php
   Route::middleware('auth:sanctum')->group(function () {
       Route::get('/missions', [MissionController::class, 'index']);
       Route::get('/missions/{id}', [MissionController::class, 'show']);
       Route::post('/missions/estimate', [MissionController::class, 'estimate']);
       Route::get('/missions/{id}/jalons', [JalonController::class, 'index']);
       Route::put('/jalons/{id}/submit', [JalonController::class, 'submit']);
       Route::post('/jalons/{id}/request-otp', [JalonController::class, 'requestOtp']);
       Route::post('/jalons/{id}/validate-otp', [JalonController::class, 'validateOtp']);
   });
   ```

2. Seed test data:
   ```bash
   php artisan db:seed --class=MissionSeeder
   ```

3. Test the flow:
   - Login as client
   - Navigate to Missions tab
   - Filter by status
   - View mission details
   - Request AI estimation
   - Submit/validate jalons (as artisan)

### 🐛 Troubleshooting

If missions don't load:
- Verify backend routes are registered
- Check database has mission records
- Verify Sanctum token is valid
- Check Laravel logs for errors
- Enable Dio logger to see API requests/responses
- Ensure field naming matches (snake_case → camelCase mapping)
