# Artisan Feature - Complete Implementation

## ✅ Status: COMPLETE & CONNECTED

The artisan feature is now fully implemented and connected to the backend API.

---

## 📱 Frontend Components

### 1. Score Dashboard Screen
**Location**: `frontend/lib/features/artisan/presentation/screens/score_dashboard_screen.dart`

**Features**:
- ✅ **N'Zassa Score Display**
  - Large animated score display (0-100)
  - Color-coded gradient background (red → orange → blue → green)
  - Badge level indicator (Bronze, Silver, Gold)
  - Score label (Excellent, Très Bien, Bien, À améliorer)

- ✅ **Score Statistics Cards**
  - Projects completed count
  - Average rating with stars
  - Completion rate percentage
  - Total reviews count

- ✅ **Score Breakdown**
  - Reliability score (40% weight)
  - Integrity score (30% weight)
  - Quality score (15% weight)
  - Responsiveness score (10% weight)
  - Professionalism score (5% weight)
  - Each with progress bar and color coding

- ✅ **Score Evolution Chart**
  - Line chart showing score history over time
  - Uses fl_chart package
  - Gradient fill under the line
  - Interactive data points

- ✅ **Improvement Tips**
  - Personalized suggestions based on weak areas
  - Icon-based presentation
  - Only shown when score < 75 or new artisan

- ✅ **Recent History**
  - Last 5 score changes
  - Shows increase/decrease with icons
  - Reason for each change
  - Relative timestamps

### 2. Score Controller
**Location**: `frontend/lib/shared/controllers/score_controller.dart`

**State Management**:
```dart
- artisanScore: Rx<ArtisanScore?>
- scoreHistory: RxList<ScoreHistory>
- reviews: RxList<Review>
- reviewStats: Rx<ReviewStats?>
- isLoading, isLoadingHistory, isLoadingReviews
```

**Methods**:
- `fetchArtisanScore(int artisanId)` - Get current score
- `fetchScoreHistory(int artisanId)` - Get score changes
- `fetchReviews(int artisanId)` - Get reviews with pagination
- `fetchReviewStats(int artisanId)` - Get review statistics
- `submitReview(CreateReviewRequest)` - Submit new review
- `respondToReview(int reviewId, String response)` - Artisan responds
- `uploadReviewPhotos(List<String> paths)` - Upload review images
- `refreshScoreData(int artisanId)` - Refresh all data

### 3. Score Service
**Location**: `frontend/lib/core/network/score_service.dart`

**Fixed API Endpoints** ✅:
- ✅ `GET /scores/{artisanId}` - Get artisan score (was `/artisans/{id}/score`)
- ✅ `GET /scores/{artisanId}/history` - Get score history (was `/artisans/{id}/score/history`)
- ✅ `GET /reviews` - Get reviews (was `/artisans/{id}/reviews`)
- ✅ `POST /reviews` - Create review
- ✅ `POST /reviews/{id}/respond` - Respond to review
- ✅ `POST /reviews/upload-photos` - Upload review photos

**All methods use ApiResponse<T> wrapper for consistent error handling.**

### 4. Scoring Models
**Location**: `frontend/lib/shared/models/scoring_model.dart`

**Models**:
- `ArtisanScore` - Main score object with all components
- `ScoreHistory` - Historical score changes
- `Review` - Customer reviews
- `ReviewStats` - Review statistics
- `CreateReviewRequest` - Review submission DTO

---

## 🔧 Backend API

### Score Controller
**Location**: `backend/app/Http/Controllers/Api/V1/ScoreController.php`

**Endpoints**:

#### 1. Get Artisan Score
```http
GET /api/v1/scores/{artisanId}
```

**Response**:
```json
{
  "id": 1,
  "artisan_id": 5,
  "total_score": 85.5,
  "reliability_score": 90,
  "integrity_score": 85,
  "quality_score": 80,
  "responsiveness_score": 88,
  "professionalism_score": 92,
  "projects_completed": 25,
  "average_rating": 4.5,
  "completion_rate": 95.5,
  "badge_level": "gold",
  "created_at": "2026-01-15T10:00:00Z",
  "updated_at": "2026-02-18T15:30:00Z"
}
```

#### 2. Calculate/Recalculate Score
```http
POST /api/v1/scores/{artisanId}/calculate
```

**Triggers**:
- Project completion
- Review submission
- Token redemption
- Milestone validation
- Manual admin recalculation

**Score Calculation Formula**:
```
Total Score =
  (Reliability × 0.40) +
  (Integrity × 0.30) +
  (Quality × 0.15) +
  (Responsiveness × 0.10) +
  (Professionalism × 0.05)
```

**Component Calculations**:
- **Reliability**: On-time project completion rate
- **Integrity**: Proper material token usage
- **Quality**: Average customer rating (1-5 stars → 0-100)
- **Responsiveness**: Quote response time + message response
- **Professionalism**: Profile completion + dispute rate

#### 3. Get Score History
```http
GET /api/v1/scores/{artisanId}/history?limit=10
```

**Response**:
```json
[
  {
    "id": 15,
    "artisan_score_id": 1,
    "score_before": 80.0,
    "score_after": 85.5,
    "change_reason": "Projet complété avec succès",
    "reliability_change": +5.0,
    "integrity_change": 0,
    "quality_change": +2.5,
    "changed_by_user_id": null,
    "created_at": "2026-02-18T15:30:00Z"
  }
]
```

### Review Controller
**Location**: `backend/app/Http/Controllers/Api/V1/ReviewController.php`

**Endpoints**:

#### 1. List Reviews
```http
GET /api/v1/reviews?artisan_id={id}&limit=10&offset=0
```

#### 2. Create Review
```http
POST /api/v1/reviews
Content-Type: application/json

{
  "project_id": 5,
  "rating": 5,
  "title": "Excellent travail",
  "comment": "L'artisan a fait un travail remarquable...",
  "photo_urls": ["url1.jpg", "url2.jpg"]
}
```

#### 3. Respond to Review (Artisan Only)
```http
POST /api/v1/reviews/{id}/respond

{
  "response": "Merci beaucoup pour votre avis..."
}
```

#### 4. Upload Review Photos
```http
POST /api/v1/reviews/upload-photos
Content-Type: multipart/form-data

photos[0]: file
photos[1]: file
```

---

## 🎨 UI/UX Features

### Color Coding System

**Score Ranges**:
- 90-100: Green gradient (Excellent)
- 75-89: Blue gradient (Très Bien)
- 60-74: Orange gradient (Bien)
- 0-59: Red gradient (À améliorer)

**Badge Levels**:
| Badge | Score | Projects | Color |
|-------|-------|----------|-------|
| Gold | ≥80 | ≥20 | #FFD700 |
| Silver | ≥65 | ≥10 | #C0C0C0 |
| Bronze | ≥50 | ≥5 | #CD7F32 |
| None | <50 | <5 | Gray |

### Responsive Design
- Sliver app bar with expandable header
- Pull-to-refresh on entire screen
- Smooth animations and transitions
- Adaptive card layouts
- Material Design 3 components

### Chart Features
- Smooth curve interpolation
- Gradient fill below line
- Interactive tooltips (fl_chart)
- No visible grid for clean look
- Auto-scaling based on data range

---

## 🔗 Integration Points

### 1. Score Calculation Triggers

**Automatic Updates**:
```php
// After project completion
ScoreCalculationJob::dispatch($artisanId, 'project_completed');

// After review submission
ScoreCalculationJob::dispatch($artisanId, 'review_received');

// After token redemption
ScoreCalculationJob::dispatch($artisanId, 'token_redeemed');

// After milestone validation
ScoreCalculationJob::dispatch($artisanId, 'milestone_validated');
```

**Manual Updates**:
- Admin can recalculate from Filament panel
- Admin can override score with reason (logged in ScoreHistory)

### 2. Dependencies in App Flow

**Score Display Locations**:
1. Artisan dashboard (main feature)
2. Artisan public profile (for clients)
3. Search results (badge indicator)
4. Quote cards (score badge)

**Related Features**:
- Reviews system (affects Quality score)
- Projects (affects Reliability score)
- Material tokens (affects Integrity score)
- Messaging (affects Responsiveness score)
- Profile completion (affects Professionalism score)

---

## 📊 Database Schema

### artisan_scores Table
```sql
CREATE TABLE artisan_scores (
    id BIGINT PRIMARY KEY,
    artisan_id BIGINT UNIQUE,
    total_score DECIMAL(5,2) DEFAULT 0,
    reliability_score DECIMAL(5,2) DEFAULT 0,
    integrity_score DECIMAL(5,2) DEFAULT 0,
    quality_score DECIMAL(5,2) DEFAULT 0,
    responsiveness_score DECIMAL(5,2) DEFAULT 0,
    professionalism_score DECIMAL(5,2) DEFAULT 0,
    projects_completed INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    completion_rate DECIMAL(5,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    badge_level ENUM('none','bronze','silver','gold') DEFAULT 'none',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES users(id)
);
```

### score_histories Table
```sql
CREATE TABLE score_histories (
    id BIGINT PRIMARY KEY,
    artisan_score_id BIGINT,
    score_before DECIMAL(5,2),
    score_after DECIMAL(5,2),
    change_reason VARCHAR(255),
    reliability_change DECIMAL(5,2) DEFAULT 0,
    integrity_change DECIMAL(5,2) DEFAULT 0,
    quality_change DECIMAL(5,2) DEFAULT 0,
    responsiveness_change DECIMAL(5,2) DEFAULT 0,
    professionalism_change DECIMAL(5,2) DEFAULT 0,
    changed_by_user_id BIGINT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (artisan_score_id) REFERENCES artisan_scores(id),
    FOREIGN KEY (changed_by_user_id) REFERENCES users(id)
);
```

### reviews Table
```sql
CREATE TABLE reviews (
    id BIGINT PRIMARY KEY,
    project_id BIGINT UNIQUE,
    reviewer_id BIGINT,
    artisan_id BIGINT,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255),
    comment TEXT,
    photo_urls JSON NULL,
    artisan_response TEXT NULL,
    response_created_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (artisan_id) REFERENCES users(id)
);
```

---

## 🧪 Testing Guide

### 1. Test Score Display
```dart
// Navigate to score dashboard
Get.to(() => ScoreDashboardScreen(artisanId: 5));

// Or for current user (artisan role required)
Get.to(() => ScoreDashboardScreen());
```

### 2. Test Score Calculation
```bash
# Via API
curl -X POST http://localhost:8000/api/v1/scores/5/calculate \
  -H "Authorization: Bearer YOUR_TOKEN"

# Via Filament admin panel
# Navigate to Artisan Scores → Select artisan → Recalculate Score
```

### 3. Test Review Submission
```dart
final request = CreateReviewRequest(
  projectId: 10,
  rating: 5,
  title: 'Excellent',
  comment: 'Très satisfait du travail',
  photoUrls: ['url1.jpg', 'url2.jpg'],
);

await scoreController.submitReview(request);
```

### 4. Test Score History
```bash
# Get last 10 score changes
curl http://localhost:8000/api/v1/scores/5/history?limit=10 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🎯 Key Improvements Made

### Before
- ❌ Score endpoints used incorrect paths (`/artisans/{id}/score`)
- ❌ ScoreService created new instance instead of DI
- ❌ No connection between frontend and backend

### After
- ✅ All endpoints match backend routes (`/scores/{id}`)
- ✅ ScoreService uses Get.find<ScoreService>()
- ✅ Fully connected and functional
- ✅ Comprehensive error handling
- ✅ Loading states for better UX
- ✅ Pull-to-refresh support
- ✅ Beautiful animations and charts

---

## 📝 Usage Examples

### Display Artisan's Own Score
```dart
// In artisan dashboard
class ArtisanDashboard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        ElevatedButton(
          onPressed: () => Get.to(() => ScoreDashboardScreen()),
          child: Text('Voir mon score N\'Zassa'),
        ),
      ],
    );
  }
}
```

### Display Other Artisan's Score
```dart
// In artisan public profile
class ArtisanProfile extends StatelessWidget {
  final int artisanId;

  @override
  Widget build(BuildContext context) {
    return ElevatedButton(
      onPressed: () => Get.to(() => ScoreDashboardScreen(artisanId: artisanId)),
      child: Text('Voir le score'),
    );
  }
}
```

### Submit Review After Project Completion
```dart
// After project completion
final photos = await scoreController.uploadReviewPhotos(imagePaths);

final request = CreateReviewRequest(
  projectId: project.id,
  rating: selectedRating,
  title: titleController.text,
  comment: commentController.text,
  photoUrls: photos,
);

final success = await scoreController.submitReview(request);

if (success) {
  Get.snackbar('Succès', 'Avis publié avec succès');
  // Score will be automatically recalculated
}
```

---

## 🚀 Future Enhancements (Optional)

- [ ] Real-time score updates via WebSockets
- [ ] Leaderboard for top artisans
- [ ] Score prediction based on current trends
- [ ] Detailed analytics per score component
- [ ] Comparison with average scores in trade
- [ ] Achievement badges for milestones
- [ ] Monthly score reports via email
- [ ] Score improvement roadmap suggestions

---

## ✅ Completion Checklist

- [x] Score dashboard screen created
- [x] Score controller implemented
- [x] Score service endpoints fixed
- [x] Connected to backend API
- [x] Dependency injection configured
- [x] Error handling implemented
- [x] Loading states added
- [x] Pull-to-refresh support
- [x] Charts and animations
- [x] Improvement tips feature
- [x] History display
- [x] Review submission
- [x] Review response (artisan)
- [x] Photo uploads

---

**Artisan Feature Status**: ✅ **100% COMPLETE & CONNECTED**

Last updated: 2026-02-18 (Phase 6 completion)
