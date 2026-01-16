# Requirements Document: ProSartisan Platform Implementation

## Introduction

ProSartisan is a marketplace platform connecting clients with skilled artisans (plumbers, electricians, masons) in Côte d'Ivoire. The platform implements a secure escrow system with material tokens (jetons), GPS-based proximity matching, milestone-based project tracking, and a reputation scoring system (N'Zassa score). The system uses Domain-Driven Design with 6 bounded contexts to ensure scalability and maintainability.

## Glossary

- **System**: The ProSartisan platform (backend API + mobile app + backoffice)
- **Client**: A user seeking artisan services
- **Artisan**: A skilled tradesperson (plumber, electrician, mason) offering services
- **Fournisseur**: A supplier providing construction materials
- **Référent_de_Zone**: A trusted third-party validator for high-value projects
- **Mission**: A work request created by a client
- **Devis**: A quote submitted by an artisan for a mission
- **Séquestre**: Escrow account holding client funds during a project
- **Jeton_Matériel**: Digital material token redeemable at suppliers
- **Chantier**: Active worksite/project
- **Jalon**: Project milestone requiring validation
- **Score_N'Zassa**: Artisan reputation score (0-100)
- **KYC**: Know Your Customer identity verification
- **Mobile_Money**: Payment services (Wave, Orange Money, MTN)
- **GPS_Coordinates**: Geographic location (latitude, longitude)
- **Floutage**: GPS coordinate blurring (50m radius)
- **Clustering**: Visual grouping of nearby artisans on map
- **OTP**: One-Time Password for validation
- **XOF**: West African CFA franc currency

## Requirements

### Requirement 1: User Registration and Authentication

**User Story:** As a user (Client, Artisan, Fournisseur), I want to register and authenticate securely, so that I can access platform services.

#### Acceptance Criteria

1. WHEN a user submits registration information, THE System SHALL create a user account with a unique identifier
2. WHEN an Artisan or Fournisseur registers, THE System SHALL require KYC documents (CNI or Passport and selfie)
3. WHEN a user attempts to authenticate, THE System SHALL verify credentials and issue a JWT token
4. WHEN an Artisan account is not KYC-verified, THE System SHALL prevent the Artisan from accepting missions
5. IF authentication fails three consecutive times, THEN THE System SHALL temporarily lock the account for 15 minutes
6. THE System SHALL support two-factor authentication via SMS OTP

### Requirement 2: Artisan Search and Discovery

**User Story:** As a Client, I want to search for artisans by trade and location, so that I can find qualified professionals nearby.

#### Acceptance Criteria

1. WHEN a Client searches for artisans, THE System SHALL filter results by trade category (plumber, electrician, mason)
2. WHEN displaying artisans on a map, THE System SHALL show artisans within 1km with golden markers at the top of results
3. WHEN multiple artisans are in close proximity, THE System SHALL cluster them visually on the map
4. WHEN displaying artisan locations, THE System SHALL blur GPS_Coordinates to a 50m radius
5. WHEN a Client views artisan profiles, THE System SHALL display the Score_N'Zassa, trade category, and average rating
6. THE System SHALL sort search results by proximity first, then by Score_N'Zassa

### Requirement 3: Mission Creation and Quote Management

**User Story:** As a Client, I want to create missions and receive quotes from artisans, so that I can compare offers and select the best option.

#### Acceptance Criteria

1. WHEN a Client creates a Mission, THE System SHALL require a description, category, location, and budget range
2. WHEN a Mission is created, THE System SHALL notify all artisans within 5km of the mission location
3. WHEN an Artisan submits a Devis, THE System SHALL include itemized costs for materials and labor
4. WHEN a Client has received quotes, THE System SHALL limit active quotes to a maximum of 3 per Mission
5. WHEN a Client accepts a Devis, THE System SHALL reveal the exact GPS_Coordinates to the Artisan
6. WHEN a Client accepts a Devis, THE System SHALL reject all other pending quotes for that Mission
7. WHEN a Devis is accepted, THE System SHALL initiate the escrow process

### Requirement 4: Escrow and Fund Management

**User Story:** As a Client, I want my payment to be held securely in escrow, so that funds are only released when work is completed satisfactorily.

#### Acceptance Criteria

1. WHEN a Devis is accepted, THE System SHALL block the total amount in a Séquestre account
2. WHEN funds are blocked, THE System SHALL fragment the Séquestre into 65% for materials and 35% for labor
3. WHEN fragmentation is complete, THE System SHALL generate a Jeton_Matériel for the materials portion
4. THE System SHALL integrate with Mobile_Money services (Wave, Orange Money, MTN) for fund transfers
5. WHEN a payment transaction fails, THE System SHALL retry up to 3 times with exponential backoff
6. WHEN a Séquestre is created, THE System SHALL record the transaction with timestamp and amount in XOF

### Requirement 5: Material Token (Jeton) System

**User Story:** As an Artisan, I want to use material tokens at suppliers, so that I can purchase materials without upfront cash.

#### Acceptance Criteria

1. WHEN a Jeton_Matériel is generated, THE System SHALL create a unique code in format PA-XXXX
2. WHEN a Jeton_Matériel is generated, THE System SHALL set an expiration date of 7 days from creation
3. WHEN an Artisan presents a Jeton_Matériel to a Fournisseur, THE System SHALL verify that both parties are within 100m of each other using GPS_Coordinates
4. IF the Artisan and Fournisseur are more than 100m apart, THEN THE System SHALL reject the Jeton_Matériel validation
5. WHEN a Jeton_Matériel is validated, THE System SHALL allow partial redemption of the token amount
6. WHEN a Jeton_Matériel is fully redeemed, THE System SHALL transfer funds to the Fournisseur account at J+1
7. WHEN a Jeton_Matériel expires, THE System SHALL return unused funds to the materials portion of the Séquestre

### Requirement 6: Worksite Management and Milestone Validation

**User Story:** As an Artisan, I want to submit milestone proofs and receive payment upon validation, so that I get paid progressively as work advances.

#### Acceptance Criteria

1. WHEN a Chantier starts, THE System SHALL create Jalon entries based on the accepted Devis milestones
2. WHEN an Artisan submits a Jalon proof, THE System SHALL require a GPS-tagged photo with timestamp
3. WHEN a Jalon proof is submitted, THE System SHALL notify the Client for validation
4. WHEN a Client receives a Jalon notification, THE System SHALL provide 48 hours for validation or contestation
5. IF a Client does not respond within 48 hours, THEN THE System SHALL automatically validate the Jalon
6. WHEN a Jalon is validated, THE System SHALL release the corresponding labor payment to the Artisan via Mobile_Money
7. WHEN all Jalons are validated, THE System SHALL mark the Chantier as completed

### Requirement 7: Reputation and N'Zassa Score Calculation

**User Story:** As an Artisan, I want my reputation score to reflect my performance, so that I can attract more clients and access micro-credit.

#### Acceptance Criteria

1. WHEN a Chantier is completed, THE System SHALL recalculate the Artisan's Score_N'Zassa
2. WHEN calculating Score_N'Zassa, THE System SHALL weight components as: Reliability 40%, Integrity 30%, Quality 20%, Reactivity 10%
3. WHEN calculating Reliability, THE System SHALL compute (completed projects / accepted projects) * 100
4. WHEN calculating Integrity, THE System SHALL deduct points for fraud attempts or system circumvention
5. WHEN calculating Quality, THE System SHALL average all client ratings (1-5 stars) and normalize to 100
6. WHEN calculating Reactivity, THE System SHALL measure average response time to mission notifications
7. WHEN Score_N'Zassa exceeds 700, THE System SHALL mark the Artisan as eligible for micro-credit
8. THE System SHALL historize all Score_N'Zassa changes with timestamp and reason for audit purposes

### Requirement 8: Client Rating and Feedback

**User Story:** As a Client, I want to rate artisans after project completion, so that I can share my experience with other users.

#### Acceptance Criteria

1. WHEN a Chantier is completed, THE System SHALL prompt the Client to submit a rating (1-5 stars) and optional comment
2. WHEN a Client submits a rating, THE System SHALL validate that the rating is between 1 and 5 stars
3. WHEN a rating is submitted, THE System SHALL update the Artisan's average rating immediately
4. THE System SHALL display ratings and comments on the Artisan's public profile
5. WHEN a rating is submitted, THE System SHALL trigger a Score_N'Zassa recalculation

### Requirement 9: Dispute Resolution and Mediation

**User Story:** As a Client or Artisan, I want to report disputes and receive mediation, so that conflicts can be resolved fairly.

#### Acceptance Criteria

1. WHEN a Client or Artisan reports a dispute, THE System SHALL create a Litige record with description and evidence
2. WHEN a dispute is created, THE System SHALL freeze any pending fund releases related to the Chantier
3. WHEN a Chantier value exceeds 2,000,000 XOF, THE System SHALL assign a Référent_de_Zone for mediation
4. WHEN a Chantier value is below 2,000,000 XOF, THE System SHALL provide admin-based mediation
5. WHEN mediation is initiated, THE System SHALL notify both parties and provide a communication channel
6. WHEN arbitration is rendered, THE System SHALL execute the decision (refund, payment, or fund freeze)
7. THE System SHALL allow dispute reporting within 7 days after final Jalon validation

### Requirement 10: GPS and Location Services

**User Story:** As a user, I want accurate location-based features, so that I can find nearby artisans and validate on-site activities.

#### Acceptance Criteria

1. THE System SHALL use PostGIS for geospatial queries and distance calculations
2. WHEN calculating distance between two GPS_Coordinates, THE System SHALL use the Haversine formula
3. WHEN displaying artisan locations to Clients, THE System SHALL apply Floutage (50m radius blur)
4. WHEN validating Jeton_Matériel or Jalon proofs, THE System SHALL verify GPS_Coordinates accuracy within 10m
5. IF GPS is unavailable during validation, THEN THE System SHALL fall back to OTP SMS verification
6. THE System SHALL store GPS_Coordinates with timestamp for all location-based events

### Requirement 11: Notification System

**User Story:** As a user, I want to receive timely notifications, so that I stay informed about platform activities.

#### Acceptance Criteria

1. WHEN a Mission is created, THE System SHALL send push notifications to nearby artisans via Firebase Cloud Messaging
2. WHEN a Devis is submitted, THE System SHALL notify the Client via push notification and SMS
3. WHEN a Jalon is submitted, THE System SHALL notify the Client via push notification, SMS, and WhatsApp
4. WHEN a payment is processed, THE System SHALL send confirmation notifications to both parties
5. THE System SHALL allow users to configure notification preferences (push, SMS, WhatsApp, email)
6. WHEN a notification fails to deliver, THE System SHALL retry using alternative channels

### Requirement 12: Backoffice Administration

**User Story:** As an Admin, I want to manage users, monitor transactions, and resolve disputes, so that I can ensure platform integrity.

#### Acceptance Criteria

1. THE System SHALL provide a web-based backoffice interface for administrators
2. WHEN an Admin views user accounts, THE System SHALL display KYC status, account status, and activity history
3. WHEN an Admin suspends an account, THE System SHALL prevent that user from initiating new transactions
4. THE System SHALL provide dashboards showing transaction volumes, active missions, and dispute statistics
5. WHEN an Admin reviews a dispute, THE System SHALL display all evidence, communications, and transaction history
6. THE System SHALL allow Admins to manually adjust Score_N'Zassa with justification (logged for audit)
7. THE System SHALL export transaction reports in CSV format for financial auditing

### Requirement 13: Security and Fraud Prevention

**User Story:** As a platform operator, I want to prevent fraud and ensure secure transactions, so that users can trust the platform.

#### Acceptance Criteria

1. THE System SHALL encrypt all sensitive data (passwords, payment information) using AES-256
2. THE System SHALL validate all API requests using JWT tokens with 24-hour expiration
3. WHEN detecting suspicious activity (multiple failed logins, unusual transaction patterns), THE System SHALL flag accounts for review
4. THE System SHALL implement rate limiting of 100 requests per minute per user
5. WHEN a Jeton_Matériel validation is attempted, THE System SHALL verify GPS proximity to prevent fraud
6. THE System SHALL log all financial transactions with immutable audit trails
7. IF a user attempts to circumvent the escrow system, THEN THE System SHALL penalize their Score_N'Zassa and flag for admin review

### Requirement 14: Mobile Application Features

**User Story:** As a mobile user, I want a responsive and intuitive app, so that I can easily access platform features on the go.

#### Acceptance Criteria

1. THE System SHALL provide a Flutter mobile application for iOS and Android
2. WHEN the app launches, THE System SHALL check for authentication and redirect to login if needed
3. THE System SHALL support offline mode for viewing cached mission history and artisan profiles
4. WHEN network connectivity is restored, THE System SHALL sync pending actions (ratings, messages)
5. THE System SHALL use device GPS for location-based features with user permission
6. THE System SHALL display maps using Google Maps SDK with custom markers for artisans
7. WHEN capturing photos for Jalon proofs, THE System SHALL embed GPS_Coordinates and timestamp in EXIF data

### Requirement 15: Payment Integration and Mobile Money

**User Story:** As a user, I want to pay and receive funds via mobile money, so that I can transact without a bank account.

#### Acceptance Criteria

1. THE System SHALL integrate with Wave, Orange Money, and MTN Mobile Money APIs
2. WHEN a Client initiates payment, THE System SHALL present available Mobile_Money options based on their phone number
3. WHEN a payment is initiated, THE System SHALL redirect to the Mobile_Money provider's payment flow
4. WHEN a payment is confirmed, THE System SHALL receive a webhook callback and update the Séquestre status
5. IF a payment webhook is not received within 5 minutes, THEN THE System SHALL query the payment status via API
6. WHEN releasing funds to an Artisan or Fournisseur, THE System SHALL initiate a Mobile_Money transfer to their registered number
7. THE System SHALL charge a 5% service fee on all transactions, deducted from the Séquestre

### Requirement 16: Data Persistence and Backup

**User Story:** As a platform operator, I want reliable data storage and backups, so that no data is lost in case of system failure.

#### Acceptance Criteria

1. THE System SHALL use PostgreSQL with PostGIS extension for primary data storage
2. THE System SHALL perform automated daily backups of the database at 2:00 AM UTC
3. WHEN a backup is completed, THE System SHALL verify backup integrity and store it in a separate location
4. THE System SHALL retain daily backups for 30 days and monthly backups for 1 year
5. THE System SHALL implement database replication with a standby server for high availability
6. WHEN the primary database fails, THE System SHALL automatically failover to the standby server within 60 seconds

### Requirement 17: Performance and Scalability

**User Story:** As a platform operator, I want the system to handle growing user load, so that performance remains consistent as the platform scales.

#### Acceptance Criteria

1. WHEN searching for artisans, THE System SHALL return results within 2 seconds for queries covering up to 10km radius
2. WHEN loading a mission list, THE System SHALL paginate results with 20 items per page
3. THE System SHALL cache frequently accessed data (artisan profiles, categories) with 5-minute TTL
4. WHEN concurrent users exceed 1000, THE System SHALL maintain response times under 3 seconds for 95% of requests
5. THE System SHALL use database indexing on frequently queried fields (user_id, mission_id, GPS_Coordinates)
6. THE System SHALL implement connection pooling with a maximum of 100 concurrent database connections

### Requirement 18: Localization and Language Support

**User Story:** As a user in Côte d'Ivoire, I want the platform in French, so that I can use it in my native language.

#### Acceptance Criteria

1. THE System SHALL display all user-facing text in French
2. THE System SHALL format currency amounts in XOF with proper thousand separators (e.g., 1 000 000 FCFA)
3. THE System SHALL format dates and times according to French locale (DD/MM/YYYY HH:mm)
4. THE System SHALL use French terminology from the Glossary consistently across all interfaces
5. WHERE a user prefers English, THE System SHALL provide English translations for all interface text

### Requirement 19: Testing and Quality Assurance

**User Story:** As a developer, I want comprehensive test coverage, so that I can ensure system reliability and catch bugs early.

#### Acceptance Criteria

1. THE System SHALL include unit tests for all domain entities, value objects, and services
2. THE System SHALL include integration tests for all API endpoints
3. THE System SHALL include property-based tests for critical business logic (escrow fragmentation, score calculation, GPS validation)
4. WHEN running the test suite, THE System SHALL achieve minimum 80% code coverage
5. THE System SHALL include end-to-end tests for critical user flows (mission creation, payment, milestone validation)
6. THE System SHALL run automated tests on every code commit via CI/CD pipeline

### Requirement 20: API Documentation and Developer Experience

**User Story:** As a developer, I want clear API documentation, so that I can integrate with the platform efficiently.

#### Acceptance Criteria

1. THE System SHALL provide OpenAPI (Swagger) documentation for all REST endpoints
2. WHEN accessing API documentation, THE System SHALL display request/response examples for each endpoint
3. THE System SHALL version all API endpoints with format /api/v1/
4. THE System SHALL return consistent error responses with HTTP status codes and error messages in JSON format
5. THE System SHALL provide a sandbox environment for testing API integrations
6. THE System SHALL document all webhook events with payload schemas and retry policies
