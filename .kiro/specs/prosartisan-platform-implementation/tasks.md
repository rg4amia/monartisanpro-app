# Implementation Plan: ProSartisan Platform

## Overview

This implementation plan breaks down the ProSartisan platform into incremental, testable steps. The approach follows Domain-Driven Design principles with 6 bounded contexts. Each task builds on previous work, with regular checkpoints to ensure quality and integration.

**Technology Stack**:
- Backend: Laravel (PHP 8.2+) with DDD architecture
- Mobile: Flutter (Dart) for iOS and Android
- Database: PostgreSQL with PostGIS extension
- Testing: PHPUnit + pest-plugin-property (backend), Dart test package (mobile)

**Implementation Strategy**:
1. Start with core domain models and value objects
2. Build infrastructure layer (repositories, external services)
3. Implement application layer (use cases, event handlers)
4. Create API endpoints and mobile UI
5. Add property-based tests alongside implementation
6. Wire everything together with integration tests

## Tasks

- [x] 1. Set up project infrastructure and shared kernel
  - Configure PostgreSQL with PostGIS extension
  - Set up Laravel project structure with DDD folders (Domain, Application, Infrastructure, Http)
  - Set up Flutter project with feature-based architecture
  - Create shared value objects (MoneyAmount, GPS_Coordinates, DeviseXOF)
  - Configure testing frameworks (PHPUnit, pest-plugin-property, Dart test)
  - Set up CI/CD pipeline configuration
  - _Requirements: 16.1, 19.1, 19.2, 19.3_

- [ ] 2. Implement Identity Management Context (Backend)
  - [x] 2.1 Create User domain entities and value objects
    - Implement User, Artisan, Client, Fournisseur entities
    - Implement KYCDocuments, Email, HashedPassword, PhoneNumber value objects
    - Implement UserType, AccountStatus enums
    - _Requirements: 1.1, 1.2_
  
  - [ ]* 2.2 Write property test for user account creation
    - **Property 1: User Account Creation Uniqueness**
    - **Validates: Requirements 1.1**
  
  - [x] 2.3 Implement KYC verification domain service
    - Create KYCVerificationService interface and implementation
    - Implement document validation logic
    - _Requirements: 1.2_
  
  - [ ]* 2.4 Write property test for KYC requirement
    - **Property 2: KYC Document Requirement**
    - **Validates: Requirements 1.2**
  
  - [x] 2.5 Implement authentication domain service
    - Create AuthenticationService with JWT token generation
    - Implement OTP generation and verification
    - Implement account lockout logic after 3 failed attempts
    - _Requirements: 1.3, 1.5, 1.6_
  
  - [ ]* 2.6 Write property tests for authentication
    - **Property 3: Authentication Token Generation**
    - **Property 5: Account Lockout After Failed Attempts**
    - **Property 6: OTP Round Trip Verification**
    - **Validates: Requirements 1.3, 1.5, 1.6**
  
  - [x] 2.7 Create User repository and database migrations
    - Implement UserRepository interface and PostgreSQL implementation
    - Create users, artisan_profiles, fournisseur_profiles, kyc_verifications tables
    - Add PostGIS indexes for location queries
    - _Requirements: 1.1, 1.2_
  
  - [ ]* 2.8 Write property test for unverified artisan restriction
    - **Property 4: Unverified Artisan Mission Restriction**
    - **Validates: Requirements 1.4**

- [ ] 3. Implement Identity Management Context (API & Mobile)
  - [x] 3.1 Create registration and authentication API endpoints
    - POST /api/v1/auth/register (client, artisan, fournisseur)
    - POST /api/v1/auth/login
    - POST /api/v1/auth/otp/generate
    - POST /api/v1/auth/otp/verify
    - POST /api/v1/users/{id}/kyc (upload KYC documents)
    - _Requirements: 1.1, 1.2, 1.3, 1.6_
  
  - [x] 3.2 Implement Flutter authentication screens
    - Create login screen with email/password
    - Create registration screens for each user type
    - Create KYC document upload screen with camera integration
    - Create OTP verification screen
    - _Requirements: 1.1, 1.2, 1.3, 1.6_
  
  - [ ]* 3.3 Write integration tests for authentication flow
    - Test complete registration → KYC upload → login flow
    - Test OTP generation and verification
    - Test account lockout after failed attempts
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6_

- [x] 4. Checkpoint - Identity Context Complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Implement Marketplace Context (Backend)
  - [x] 5.1 Create Mission and Devis domain entities
    - Implement Mission aggregate with quote management
    - Implement Devis entity with DevisLine items
    - Implement TradeCategory, MissionStatus, DevisStatus value objects
    - _Requirements: 3.1, 3.3, 3.4_
  
  - [ ]* 5.2 Write property tests for mission and quote logic
    - **Property 12: Mission Required Fields Validation**
    - **Property 14: Devis Cost Structure**
    - **Property 15: Maximum Quotes Per Mission**
    - **Validates: Requirements 3.1, 3.3, 3.4**
  
  - [x] 5.3 Implement artisan search domain service
    - Create ArtisanSearchService with PostGIS proximity queries
    - Implement proximity-based sorting (≤1km first)
    - Implement trade category filtering
    - _Requirements: 2.1, 2.2, 2.6_
  
  - [ ]* 5.4 Write property tests for search functionality
    - **Property 7: Search Category Filtering**
    - **Property 8: Proximity-Based Result Ordering**
    - **Property 11: Search Result Sort Order**
    - **Validates: Requirements 2.1, 2.2, 2.6**
  
  - [x] 5.5 Implement location privacy service
    - Create LocationPrivacyService with GPS blurring (50m radius)
    - Implement coordinate revelation after quote acceptance
    - _Requirements: 2.4, 3.5_
  
  - [ ]* 5.6 Write property tests for GPS blurring
    - **Property 9: GPS Coordinate Blurring**
    - **Property 16: Coordinate Revelation After Acceptance**
    - **Validates: Requirements 2.4, 3.5_
  
  - [x] 5.7 Create Mission and Devis repositories
    - Implement MissionRepository and DevisRepository interfaces
    - Create missions, devis, devis_lines tables with PostGIS indexes
    - _Requirements: 3.1, 3.3_
  
  - [ ]* 5.8 Write property tests for quote acceptance side effects
    - **Property 17: Quote Rejection Side Effect**
    - **Property 18: Escrow Initiation Trigger**
    - **Validates: Requirements 3.6, 3.7**

- [ ] 6. Implement Marketplace Context (API & Mobile)
  - [x] 6.1 Create mission and quote API endpoints
    - POST /api/v1/missions (create mission)
    - GET /api/v1/missions (list missions with pagination)
    - GET /api/v1/missions/{id}
    - POST /api/v1/missions/{id}/quotes (submit quote)
    - POST /api/v1/quotes/{id}/accept
    - GET /api/v1/artisans/search (with filters and location)
    - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 3.5_
  
  - [x] 6.2 Implement Flutter marketplace screens
    - Create artisan search screen with map view (Google Maps SDK)
    - Implement map clustering for nearby artisans
    - Create mission creation form
    - Create quote submission form for artisans
    - Create quote list and acceptance screen for clients
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.3, 3.5_
  
  - [ ]* 6.3 Write integration tests for marketplace flow
    - Test mission creation → artisan search → quote submission → acceptance
    - Test proximity-based search results
    - Test maximum 3 quotes per mission constraint
    - _Requirements: 2.1, 2.2, 3.1, 3.3, 3.4, 3.5_

- [ ] 7. Checkpoint - Marketplace Context Complete
  - Ensure all tests pass, ask the user if questions arise.


- [ ] 8. Implement Financial Transactions Context (Backend)
  - [x] 8.1 Create Sequestre and JetonMateriel domain entities
    - Implement Sequestre aggregate with fragmentation logic
    - Implement JetonMateriel entity with validation tracking
    - Implement Transaction entity for audit trail
    - Implement MoneyAmount, JetonCode, SequestreStatus value objects
    - _Requirements: 4.1, 4.2, 5.1, 5.2_
  
  - [ ]* 8.2 Write property tests for escrow fragmentation
    - **Property 19: Escrow Fragmentation Ratio**
    - **Property 20: Jeton Generation After Fragmentation**
    - **Validates: Requirements 4.2, 4.3**
  
  - [x] 8.3 Implement escrow fragmentation domain service
    - Create EscrowFragmentationService with 65/35 split calculation
    - Implement fund release logic for materials and labor
    - _Requirements: 4.2_
  
  - [x] 8.4 Implement jeton factory and validation service
    - Create JetonFactory for jeton generation with PA-XXXX codes
    - Implement AntiFraudService with GPS proximity validation (100m)
    - Implement jeton expiration logic (7 days)
    - _Requirements: 5.1, 5.2, 5.3, 5.7_
  
  - [ ]* 8.5 Write property tests for jeton logic
    - **Property 23: Jeton Code Format and Uniqueness**
    - **Property 24: Jeton Expiration Date**
    - **Property 25: Jeton Proximity Validation**
    - **Property 26: Jeton Partial Redemption**
    - **Property 28: Expired Jeton Fund Return**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.5, 5.7**
  
  - [x] 8.6 Implement mobile money gateway adapter
    - Create MobileMoneyGateway interface
    - Implement Wave, Orange Money, MTN adapters
    - Implement retry logic (3 attempts with exponential backoff)
    - Implement webhook handling for payment confirmations
    - _Requirements: 4.4, 4.5, 15.4, 15.5_
  
  - [ ]* 8.7 Write property tests for payment logic
    - **Property 21: Payment Retry Logic**
    - **Property 78: Payment Webhook Status Update**
    - **Property 79: Payment Webhook Timeout Query**
    - **Validates: Requirements 4.5, 15.4, 15.5**
  
  - [x] 8.8 Create Financial repositories and migrations
    - Implement SequestreRepository, JetonRepository, TransactionRepository
    - Create sequestres, jetons_materiel, jeton_validations, transactions tables
    - Ensure transactions table is append-only (immutable audit log)
    - _Requirements: 4.1, 4.6, 5.1, 13.6_
  
  - [ ]* 8.9 Write property tests for transaction audit trail
    - **Property 22: Transaction Audit Trail**
    - **Property 72: Financial Transaction Audit Logging**
    - **Validates: Requirements 4.6, 13.6**

- [x] 9. Implement Financial Transactions Context (API & Mobile)
  - [x] 9.1 Create financial transaction API endpoints
    - POST /api/v1/escrow/block (initiate escrow after quote acceptance)
    - POST /api/v1/jetons/generate
    - POST /api/v1/jetons/validate (with GPS verification)
    - POST /api/v1/payments/webhook (for mobile money callbacks)
    - GET /api/v1/transactions (transaction history)
    - _Requirements: 4.1, 4.2, 5.1, 5.3, 15.4_
  
  - [x] 9.2 Implement Flutter payment and jeton screens
    - Create payment initiation screen with mobile money options
    - Create jeton display screen for artisans (show code and QR)
    - Create jeton validation screen for suppliers (scan QR, verify GPS)
    - Create transaction history screen
    - _Requirements: 4.1, 5.1, 5.3, 15.2_
  
  - [ ]* 9.3 Write integration tests for financial flow
    - Test escrow block → fragmentation → jeton generation
    - Test jeton validation with GPS proximity check
    - Test payment webhook processing
    - Test fund release to artisan and supplier
    - _Requirements: 4.1, 4.2, 4.3, 5.3, 15.4_

- [ ] 10. Checkpoint - Financial Context Complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Implement Worksite Management Context (Backend)
  - [x] 11.1 Create Chantier and Jalon domain entities
    - Implement Chantier aggregate with milestone management
    - Implement Jalon entity with proof submission and validation
    - Implement ProofOfDelivery value object with GPS and photo data
    - Implement ChantierStatus, JalonStatus enums
    - _Requirements: 6.1, 6.2_
  
  - [ ]* 11.2 Write property tests for chantier initialization
    - **Property 29: Chantier Milestone Initialization**
    - **Validates: Requirements 6.1**
  
  - [x] 11.3 Implement milestone validation domain service
    - Create MilestoneValidationService with GPS verification
    - Implement PhotoVerificationService for EXIF data extraction
    - Implement 48-hour auto-validation deadline logic
    - _Requirements: 6.2, 6.4, 6.5_
  
  - [ ]* 11.4 Write property tests for milestone validation
    - **Property 30: Jalon Proof GPS Requirement**
    - **Property 32: Jalon Auto-Validation Deadline**
    - **Property 33: Jalon Auto-Validation Execution**
    - **Validates: Requirements 6.2, 6.4, 6.5**
  
  - [x] 11.5 Implement auto-validation service with cron job
    - Create AutoValidationService to process expired deadlines
    - Set up Laravel scheduled task to run every hour
    - _Requirements: 6.5_
  
  - [x] 11.6 Create Chantier and Jalon repositories
    - Implement ChantierRepository and JalonRepository
    - Create chantiers and jalons tables
    - Add index on auto_validation_deadline for cron queries
    - _Requirements: 6.1, 6.2_
  
  - [ ]* 11.7 Write property tests for payment release and completion
    - **Property 34: Jalon Validation Payment Release**
    - **Property 35: Chantier Completion Condition**
    - **Validates: Requirements 6.6, 6.7**

- [x] 12. Implement Worksite Management Context (API & Mobile)
  - [x] 12.1 Create worksite management API endpoints
    - POST /api/v1/chantiers (start chantier after escrow)
    - GET /api/v1/chantiers/{id}
    - POST /api/v1/jalons/{id}/submit-proof (with photo upload)
    - POST /api/v1/jalons/{id}/validate
    - POST /api/v1/jalons/{id}/contest
    - _Requirements: 6.1, 6.2, 6.3_
  
  - [x] 12.2 Implement Flutter worksite screens
    - Create chantier detail screen with milestone list
    - Create photo capture screen with GPS embedding
    - Create milestone validation screen for clients
    - Create milestone proof submission screen for artisans
    - _Requirements: 6.1, 6.2, 6.3, 14.7_
  
  - [ ]* 12.3 Write integration tests for worksite flow
    - Test chantier start → milestone proof submission → validation → payment release
    - Test auto-validation after 48 hours
    - Test photo GPS extraction and verification
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 13. Checkpoint - Worksite Context Complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. Implement Reputation Management Context (Backend)
  - [x] 13.1 Create ReputationProfile domain entity
    - Implement ReputationProfile aggregate with score history
    - Implement NZassaScore value object with 0-100 validation
    - Implement ReputationMetrics value object
    - Implement ScoreSnapshot for audit trail
    - _Requirements: 7.1, 7.2, 7.8_
  
  - [x]* 13.2 Write property test for score range validation
    - Test that NZassaScore constructor rejects values outside 0-100
    - _Requirements: 7.2_
  
  - [x] 13.3 Implement score calculation domain service
    - Create ScoreCalculationService with weighted formula
    - Implement reliability calculation (completed / accepted * 100)
    - Implement integrity calculation with fraud penalties
    - Implement quality calculation (average rating / 5 * 100)
    - Implement reactivity calculation (inverse of response time)
    - _Requirements: 7.2, 7.3, 7.4, 7.5, 7.6_
  
  - [x]* 13.4 Write property tests for score calculations
    - **Property 37: N'Zassa Score Weighted Calculation**
    - **Property 38: Reliability Score Formula**
    - **Property 39: Integrity Fraud Penalty**
    - **Property 40: Quality Score Normalization**
    - **Property 41: Reactivity Score Calculation**
    - **Validates: Requirements 7.2, 7.3, 7.4, 7.5, 7.6**
  
  - [x] 13.4 Implement metrics aggregation service
    - Create MetricsAggregationService to query across contexts
    - Aggregate completed/accepted projects from Chantier
    - Aggregate ratings from Rating table
    - Calculate average response time from Mission notifications
    - _Requirements: 7.1, 7.3, 7.5, 7.6_
  
  - [x] 13.5 Create Reputation repositories and migrations
    - Implement ReputationRepository
    - Create reputation_profiles, score_history, ratings tables
    - Add indexes on artisan_id and current_score
    - _Requirements: 7.1, 7.8, 8.2_
  
  - [x]* 13.6 Write property tests for reputation features
    - **Property 36: Score Recalculation Trigger**
    - **Property 42: Micro-Credit Eligibility Threshold**
    - **Property 43: Score History Audit Trail**
    - **Validates: Requirements 7.1, 7.7, 7.8**

- [x] 14. Implement Reputation Management Context (API & Mobile)
  - [x] 14.1 Create reputation API endpoints
    - GET /api/v1/artisans/{id}/reputation
    - GET /api/v1/artisans/{id}/score-history
    - POST /api/v1/missions/{id}/rate (submit rating)
    - GET /api/v1/artisans/{id}/ratings
    - _Requirements: 7.1, 7.8, 8.2, 8.3_
  
  - [x] 14.2 Implement Flutter reputation screens
    - Create artisan reputation detail screen with score breakdown
    - Create rating submission screen (1-5 stars + comment)
    - Display score history chart
    - _Requirements: 7.1, 8.2, 8.4_
  
  - [ ]* 14.3 Write property tests for rating logic
    - **Property 44: Rating Value Validation**
    - **Property 45: Rating Average Update**
    - **Property 47: Rating Score Recalculation Trigger**
    - **Validates: Requirements 8.2, 8.3, 8.5**
  
  - [ ]* 14.4 Write integration tests for reputation flow
    - Test chantier completion → score recalculation
    - Test rating submission → average update → score recalculation
    - Test score history audit trail
    - _Requirements: 7.1, 7.8, 8.2, 8.3, 8.5_

- [ ] 15. Checkpoint - Reputation Context Complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 16. Implement Dispute Resolution Context (Backend)
  - [x] 16.1 Create Litige domain entity
    - Implement Litige aggregate with mediation and arbitration
    - Implement Mediation and Arbitration entities
    - Implement DisputeType, DisputeStatus, ArbitrationDecision value objects
    - _Requirements: 9.1, 9.2_
  
  - [ ]* 16.2 Write property tests for dispute creation
    - **Property 48: Dispute Record Creation**
    - **Property 49: Dispute Fund Freeze**
    - **Validates: Requirements 9.1, 9.2**
  
  - [x] 16.3 Implement mediation and arbitration services
    - Create MediationService with mediator assignment logic
    - Implement high-value dispute detection (> 2M XOF)
    - Create ArbitrationService with decision execution
    - _Requirements: 9.3, 9.4, 9.6_
  
  - [ ]* 16.4 Write property tests for dispute resolution
    - **Property 50: High-Value Dispute Mediator Assignment**
    - **Property 52: Arbitration Decision Execution**
    - **Property 53: Dispute Reporting Time Window**
    - **Validates: Requirements 9.3, 9.4, 9.6, 9.7**
  
  - [x] 16.5 Create Litige repository and migrations
    - Implement LitigeRepository
    - Create litiges and mediation_communications tables
    - _Requirements: 9.1_
  
  - [ ]* 16.6 Write property test for mediation notifications
    - **Property 51: Mediation Party Notification**
    - **Validates: Requirements 9.5**

- [x] 17. Implement Dispute Resolution Context (API & Mobile)
  - [x] 17.1 Create dispute resolution API endpoints
    - POST /api/v1/disputes (report dispute)
    - GET /api/v1/disputes/{id}
    - POST /api/v1/disputes/{id}/mediation/start
    - POST /api/v1/disputes/{id}/mediation/message
    - POST /api/v1/disputes/{id}/arbitration/render
    - _Requirements: 9.1, 9.5, 9.6_
  
  - [x] 17.2 Implement Flutter dispute screens
    - Create dispute reporting form with evidence upload
    - Create mediation chat screen
    - Create dispute detail screen for admins
    - _Requirements: 9.1, 9.5_
  
  - [ ]* 17.3 Write integration tests for dispute flow
    - Test dispute creation → fund freeze → mediation → resolution
    - Test high-value dispute assignment to referent
    - Test arbitration decision execution
    - _Requirements: 9.1, 9.2, 9.3, 9.5, 9.6_

- [ ] 18. Checkpoint - Dispute Context Complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 19. Implement Notification System
  - [x] 19.1 Create notification infrastructure
    - Implement Firebase Cloud Messaging adapter
    - Implement SMS gateway adapter (Twilio or local provider)
    - Implement WhatsApp Business API adapter
    - Implement email service adapter
    - _Requirements: 11.1, 11.2, 11.3, 11.4_
  
  - [x] 19.2 Implement notification service with retry logic
    - Create NotificationService with channel fallback
    - Implement retry logic (push → SMS → WhatsApp → email)
    - Implement user notification preferences
    - _Requirements: 11.5, 11.6_
  
  - [ ]* 19.3 Write property tests for notification logic
    - **Property 58: Mission Creation Notification Delivery**
    - **Property 59: Devis Submission Notification**
    - **Property 60: Jalon Multi-Channel Notification**
    - **Property 61: Payment Confirmation Notification**
    - **Property 62: Notification Preference Respect**
    - **Property 63: Notification Retry on Failure**
    - **Validates: Requirements 11.1, 11.2, 11.3, 11.4, 11.5, 11.6**
  
  - [x] 19.4 Wire notification triggers to domain events
    - Listen to MissionCreated → notify nearby artisans
    - Listen to QuoteSubmitted → notify client
    - Listen to MilestoneProofSubmitted → notify client
    - Listen to LaborPaymentReleased → notify artisan
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

- [x] 20. Implement Backoffice Administration
  - [x] 20.1 Create React backoffice application
    - Set up React project with TypeScript
    - Create admin authentication and authorization
    - Implement dashboard with statistics
    - _Requirements: 12.1, 12.4_
  
  - [x] 20.2 Implement user management screens
    - Create user list with filters (type, status, KYC)
    - Create user detail view with KYC documents
    - Implement account suspension functionality
    - _Requirements: 12.2, 12.3_
  
  - [ ]* 20.3 Write property tests for admin actions
    - **Property 64: Admin User View Data Completeness**
    - **Property 65: Suspended Account Transaction Prevention**
    - **Validates: Requirements 12.2, 12.3**
  
  - [x] 20.4 Implement dispute management screens
    - Create dispute list with filters
    - Create dispute detail view with evidence and communications
    - Implement arbitration decision form
    - _Requirements: 12.5_
  
  - [ ]* 20.5 Write property test for dispute view completeness
    - **Property 66: Admin Dispute View Evidence Completeness**
    - **Validates: Requirements 12.5**
  
  - [x] 20.6 Implement reputation management screens
    - Create artisan reputation dashboard
    - Implement manual score adjustment with justification
    - Create transaction export functionality (CSV)
    - _Requirements: 12.6, 12.7_
  
  - [ ]* 20.7 Write property tests for admin reputation actions
    - **Property 67: Admin Score Adjustment Audit**
    - **Property 68: Transaction Export Data Accuracy**
    - **Validates: Requirements 12.6, 12.7**

- [x] 21. Implement Security and Cross-Cutting Concerns
  - [x] 21.1 Implement API authentication and authorization
    - Create JWT middleware for all protected endpoints
    - Implement role-based access control (client, artisan, admin)
    - Implement rate limiting middleware (100 req/min)
    - _Requirements: 13.2, 13.4_
  
  - [ ]* 21.2 Write property tests for security features
    - **Property 69: JWT Token Validation**
    - **Property 71: Rate Limiting Enforcement**
    - **Validates: Requirements 13.2, 13.4**
  
  - [x] 21.3 Implement fraud detection service
    - Create SuspiciousActivityDetector with pattern matching
    - Implement account flagging for review
    - Implement escrow circumvention detection
    - _Requirements: 13.3, 13.7_
  
  - [ ]* 21.4 Write property tests for fraud detection
    - **Property 70: Suspicious Activity Flagging**
    - **Property 73: Escrow Circumvention Penalty**
    - **Validates: Requirements 13.3, 13.7**
  
  - [x] 21.5 Implement data encryption
    - Encrypt sensitive fields (passwords, payment info) with AES-256
    - Implement secure file storage for KYC documents and photos
    - _Requirements: 13.1_

- [-] 22. Implement Mobile App Features
  - [x] 22.1 Implement offline mode and sync
    - Create local SQLite cache for missions and profiles
    - Implement offline data access
    - Implement sync service for pending actions
    - _Requirements: 14.3, 14.4_
  
  - [ ]* 22.2 Write property tests for offline functionality
    - **Property 75: Offline Data Accessibility**
    - **Property 76: Online Sync After Reconnection**
    - **Validates: Requirements 14.3, 14.4**
  
  - [-] 22.3 Implement app authentication flow
    - Create splash screen with auth check
    - Implement token refresh logic
    - Redirect unauthenticated users to login
    - _Requirements: 14.2_
  
  - [ ]* 22.4 Write property test for authentication redirect
    - **Property 74: Unauthenticated User Redirection**
    - **Validates: Requirements 14.2**
  
  - [ ] 22.5 Implement photo capture with EXIF embedding
    - Use camera plugin with GPS permission
    - Embed GPS coordinates and timestamp in EXIF data
    - _Requirements: 14.7_
  
  - [ ]* 22.6 Write property test for photo EXIF data
    - **Property 77: Photo EXIF Data Embedding**
    - **Validates: Requirements 14.7**

- [ ] 23. Implement Localization and Formatting
  - [ ] 23.1 Set up French localization
    - Create French translation files for all UI text
    - Implement currency formatting (XOF with thousand separators)
    - Implement date formatting (DD/MM/YYYY HH:mm)
    - _Requirements: 18.1, 18.2, 18.3_
  
  - [ ]* 23.2 Write property tests for formatting
    - **Property 83: French Text Display**
    - **Property 84: XOF Currency Formatting**
    - **Property 85: French Date Format**
    - **Property 86: Glossary Term Consistency**
    - **Validates: Requirements 18.1, 18.2, 18.3, 18.4**
  
  - [ ] 23.3 Implement optional English localization
    - Create English translation files
    - Implement language preference setting
    - _Requirements: 18.5_
  
  - [ ]* 23.4 Write property test for English localization
    - **Property 87: English Localization Option**
    - **Validates: Requirements 18.5**

- [ ] 24. Implement API Documentation and Standards
  - [ ] 24.1 Generate OpenAPI documentation
    - Use Laravel OpenAPI generator
    - Document all endpoints with request/response examples
    - _Requirements: 20.1, 20.2_
  
  - [ ] 24.2 Implement consistent error responses
    - Create error response formatter middleware
    - Ensure all errors return JSON with error, message, status_code
    - _Requirements: 20.4_
  
  - [ ]* 24.3 Write property tests for API standards
    - **Property 88: API Endpoint Versioning**
    - **Property 89: Consistent Error Response Format**
    - **Validates: Requirements 20.3, 20.4**

- [ ] 25. Implement Geolocation Features
  - [ ] 25.1 Implement GPS utilities
    - Create Haversine distance calculation function
    - Implement GPS accuracy validation (< 10m)
    - Implement GPS fallback to OTP when unavailable
    - _Requirements: 10.2, 10.4, 10.5_
  
  - [ ]* 25.2 Write property tests for GPS features
    - **Property 54: Haversine Distance Calculation**
    - **Property 55: GPS Accuracy Validation**
    - **Property 56: GPS Fallback to OTP**
    - **Property 57: GPS Timestamp Recording**
    - **Validates: Requirements 10.2, 10.4, 10.5, 10.6**

- [ ] 26. Implement Pagination and Performance
  - [ ] 26.1 Implement pagination for list endpoints
    - Add pagination to missions, artisans, transactions lists
    - Set page size to 20 items
    - _Requirements: 17.2_
  
  - [ ]* 26.2 Write property test for pagination
    - **Property 82: Mission List Pagination**
    - **Validates: Requirements 17.2**
  
  - [ ] 26.3 Implement caching strategy
    - Cache artisan profiles with 5-minute TTL
    - Cache trade categories and static data
    - _Requirements: 17.3_
  
  - [ ] 26.4 Add database indexes for performance
    - Index frequently queried fields (user_id, mission_id, status)
    - Add PostGIS spatial indexes for location queries
    - _Requirements: 17.5_

- [ ] 27. Final Integration and End-to-End Testing
  - [ ] 27.1 Wire all contexts together with event handlers
    - QuoteAccepted → block funds → fragment → generate jeton
    - MilestoneValidated → release labor payment
    - ChantierCompleted → recalculate score
    - DisputeReported → freeze funds
    - _Requirements: 3.7, 4.1, 4.2, 4.3, 6.6, 7.1, 9.2_
  
  - [ ]* 27.2 Write end-to-end integration tests
    - Test complete mission lifecycle (creation → quote → escrow → worksite → payment → rating)
    - Test jeton lifecycle (generation → validation → fund transfer)
    - Test dispute lifecycle (report → mediation → arbitration → resolution)
    - _Requirements: All requirements_
  
  - [ ] 27.3 Implement monitoring and logging
    - Set up structured logging with correlation IDs
    - Implement metrics tracking (response times, error rates)
    - Set up alerting rules for critical failures
    - _Requirements: Testing Strategy_

- [ ] 28. Final Checkpoint - Complete Platform
  - Run full test suite (unit + property + integration + E2E)
  - Verify all 89 correctness properties pass
  - Ensure test coverage meets targets (85% overall)
  - Perform manual smoke testing of critical flows
  - Ask the user if questions arise before deployment.

## Notes

- Tasks marked with `*` are optional property-based tests that can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation and quality
- Property tests validate universal correctness properties with 100+ iterations
- Unit tests validate specific examples and edge cases
- Integration tests validate cross-context workflows
- The implementation follows DDD principles with clear bounded context separation
