# Frontend-Backend API Connection Guide

## ✅ Connection Status: CONFIGURED

The frontend Flutter app is now properly connected to the Laravel backend API.

---

## 🔧 Configuration Setup

### 1. Backend API Configuration
- **Base URL**: `http://localhost:8000/api/v1`
- **Authentication**: Laravel Sanctum (Bearer tokens)
- **CORS**: Enabled for local development

### 2. Frontend Network Configuration

#### DioClient (`frontend/lib/core/network/dio_client.dart`)
```dart
baseUrl: 'http://localhost:8000/api/v1'
```

#### Dependency Injection (`frontend/lib/core/init/app_bindings.dart`)
All services are registered on app startup:
- ✅ DioClient (singleton)
- ✅ AuthService
- ✅ KycService
- ✅ SearchService
- ✅ TradeService
- ✅ ProjectService
- ✅ PaymentService
- ✅ TokenService
- ✅ ScoreService
- ✅ MilestoneService
- ✅ DisputeService ⭐ (NEW)
- ✅ MessageService ⭐ (NEW)

#### Initialization (`frontend/lib/main.dart`)
```dart
void main() {
  // Initialize dependency injection
  AppBindings().dependencies();
  runApp(const ProsArtisanApp());
}
```

---

## 📡 Available API Endpoints

### 🔐 Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login user |
| POST | `/auth/logout` | Logout user |
| GET | `/auth/me` | Get current user |
| PUT | `/auth/profile` | Update user profile |
| POST | `/auth/send-otp` | Send OTP verification |
| POST | `/auth/verify-otp` | Verify OTP code |

### 📄 KYC
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/kyc/upload` | Upload KYC documents |
| GET | `/kyc/status` | Get KYC status |
| GET | `/kyc/documents` | Get KYC documents |

### 📍 Search & Trades
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/search/artisans` | Search artisans |
| GET | `/search/artisan` | Get artisan profile |
| GET | `/search/nearby` | Get nearby artisans |
| GET | `/sectors` | Get all sectors |
| GET | `/trades` | Get all trades |

### 📋 Projects & Quotes
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/projects` | List projects |
| POST | `/projects` | Create project |
| GET | `/projects/{id}` | Get project details |
| PUT | `/projects/{id}` | Update project |
| DELETE | `/projects/{id}` | Delete project |
| GET | `/projects/search/location` | Search by location |
| GET | `/quotes` | List quotes |
| POST | `/quotes` | Create quote |
| POST | `/quotes/{id}/send` | Send quote |
| POST | `/quotes/{id}/accept` | Accept quote |
| POST | `/quotes/{id}/reject` | Reject quote |

### 💳 Payments & Escrow
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/payments/initialize` | Initialize payment |
| GET | `/payments/verify/{ref}` | Verify payment |
| POST | `/payments/webhook` | Payment webhook (public) |

### 🎫 Material Tokens
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tokens` | List tokens |
| GET | `/tokens/{code}` | Get token by code |
| POST | `/tokens/validate` | Validate token |
| POST | `/tokens/redeem` | Redeem token |
| GET | `/tokens/{code}/redemptions` | Get redemption history |

### 🏆 Scoring & Reviews
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/scores/{artisanId}` | Get artisan score |
| POST | `/scores/{artisanId}/calculate` | Recalculate score |
| GET | `/scores/{artisanId}/history` | Get score history |
| GET | `/reviews` | List reviews |
| POST | `/reviews` | Create review |
| GET | `/reviews/{id}` | Get review |
| POST | `/reviews/{id}/respond` | Respond to review |
| POST | `/reviews/upload-photos` | Upload review photos |

### 📍 Milestones
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/milestones` | List milestones |
| POST | `/milestones` | Create milestone |
| GET | `/milestones/{id}` | Get milestone |
| PUT | `/milestones/{id}` | Update milestone |
| DELETE | `/milestones/{id}` | Delete milestone |
| POST | `/milestones/{id}/complete` | Mark complete |
| POST | `/milestones/{id}/send-otp` | Send validation OTP |
| POST | `/milestones/{id}/validate` | Validate with OTP |

### ⚖️ Disputes (Phase 6) ⭐
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/disputes` | List user disputes |
| GET | `/disputes?status=open` | Filter by status |
| GET | `/disputes/{id}` | Get dispute details + messages |
| POST | `/disputes` | Create new dispute |
| POST | `/disputes/{id}/messages` | Send dispute message |

**Request Example** (Create Dispute):
```json
{
  "project_id": 1,
  "dispute_type": "quality",
  "reason": "Travail non conforme",
  "description": "Le travail ne correspond pas aux spécifications...",
  "evidence": ["file1.jpg", "file2.jpg"]
}
```

### 💬 Messages / Chat (Phase 6) ⭐
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/messages/conversations` | List all conversations |
| GET | `/projects/{id}/messages` | Get project messages |
| POST | `/projects/{id}/messages` | Send project message |
| POST | `/messages/{id}/read` | Mark message as read |

**Request Example** (Send Message):
```json
{
  "message": "Bonjour, pouvez-vous...",
  "attachments": ["file1.jpg"]
}
```

---

## 🧪 Testing the Connection

### 1. Start the Backend Server
```bash
cd backend
php artisan serve
# Server running at: http://localhost:8000
```

### 2. Verify API is Accessible
```bash
curl http://localhost:8000/api/v1/auth/login
# Should return: {"message": "The POST method is not supported..."}
# This means the endpoint exists
```

### 3. Test Authentication Flow
```bash
# Register a user
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "+225xxxxxxxxx",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "client"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
# Returns: {"success": true, "token": "...", "user": {...}}
```

### 4. Test Protected Endpoint
```bash
# Get current user (requires token)
curl http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 5. Run Frontend App
```bash
cd frontend
flutter pub get
flutter pub run build_runner build  # Generate .g.dart files
flutter run
```

---

## 🔍 Debugging Connection Issues

### Common Issues & Solutions

#### ❌ "Connection refused" or "Network error"
**Cause**: Backend server not running or wrong URL

**Solution**:
1. Ensure backend is running: `php artisan serve`
2. Check frontend API URL in `api_constants.dart`
3. For Android emulator: Use `http://10.0.2.2:8000/api/v1`
4. For iOS simulator: Use `http://localhost:8000/api/v1`
5. For physical device: Use your computer's IP (e.g., `http://192.168.1.100:8000/api/v1`)

#### ❌ "Unauthenticated" or 401 errors
**Cause**: Missing or expired auth token

**Solution**:
1. Login first to get a token
2. Token is stored in secure storage
3. DioClient auto-adds token to headers via interceptor

#### ❌ "Service not found" when using Get.find()
**Cause**: Service not registered in dependency injection

**Solution**:
1. Verify service is in `AppBindings().dependencies()`
2. Ensure `AppBindings().dependencies()` is called in `main()`
3. Check service constructor matches registration

#### ❌ CORS errors in browser
**Cause**: CORS not configured for frontend origin

**Solution**: Add to `backend/config/cors.php`:
```php
'allowed_origins' => [
    'http://localhost:*',
    'http://127.0.0.1:*',
],
```

---

## 📱 Frontend Usage Examples

### Using DisputeService
```dart
final disputeController = Get.put(DisputeController());

// Fetch disputes
await disputeController.fetchDisputes();

// Filter by status
await disputeController.filterByStatus('open');

// Create dispute
final request = CreateDisputeRequest(
  projectId: 1,
  disputeType: 'quality',
  reason: 'Travail non conforme',
  description: 'Description...',
  evidence: ['path/to/image.jpg'],
);
await disputeController.createDispute(request);
```

### Using ChatController
```dart
final chatController = Get.put(ChatController());

// Fetch conversations
await chatController.fetchConversations();

// Open conversation
await chatController.fetchProjectMessages(projectId);

// Send message
await chatController.sendMessage(projectId, 'Bonjour!');

// Send with attachments
final images = await chatController.pickImages();
await chatController.sendMessageWithAttachments(projectId, 'Voici les photos', images);
```

---

## 🔐 Authentication Flow

1. **User registers/logs in** → Receives auth token
2. **Token stored** → SecureStorage via DioClient
3. **All API calls** → Auto-inject token via Dio interceptor
4. **Token expires** → 401 response → Auto-logout (TODO)

---

## 📊 API Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }  // Validation errors
}
```

---

## 🚀 Production Deployment Checklist

Before deploying to production:

- [ ] Update `baseUrl` in `api_constants.dart` to production URL
- [ ] Configure SSL/TLS (https)
- [ ] Set up proper CORS origins
- [ ] Enable rate limiting
- [ ] Set up monitoring (Sentry, Laravel Telescope)
- [ ] Configure push notifications (FCM)
- [ ] Test on real devices (Android + iOS)
- [ ] Verify file uploads work (disputes, chat, KYC)
- [ ] Test payment webhooks with real gateway

---

## 📝 Notes

- **All services** are lazy-loaded (created only when first used)
- **DioClient** is a singleton (shared across all services)
- **Auth token** auto-injected via Dio interceptor
- **Error handling** standardized across all services
- **File uploads** use FormData with MultipartFile
- **Image picker** supports gallery, camera, and multiple selection

---

Last updated: 2026-02-18 (Phase 6 - Disputes & Chat completed)
