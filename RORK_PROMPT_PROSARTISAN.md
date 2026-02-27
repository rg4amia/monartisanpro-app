# Prompt Rork — ProsArtisan

## 🧾 Prompt à coller dans Rork

---

Build a mobile app called **ProsArtisan** — a marketplace connecting clients, artisans (craftsmen), and hardware suppliers in Côte d'Ivoire (West Africa). The app is in **French**, uses **FCFA** as currency, and supports **3 user roles**: Client, Artisan, Fournisseur (Supplier).

---

### 🎨 Design
- Color palette: deep blue `#1A2C5B` (primary), orange `#E67E22` (artisan accent), green `#27AE60` (success/supplier), white background
- Mobile-first, clean and modern UI
- French language throughout

---

### 📱 Screens to build

#### Onboarding
- Splash screen with logo and tagline: *"L'artisan de confiance, à portée de main"*
- Phone number login screen (OTP verification)
- Role selection screen: Client / Artisan / Fournisseur (3 large cards with icons)
- KYC screen: upload CNI photo + selfie with liveness indicator

#### Client Screens
- **Home**: search bar "Décrivez votre besoin...", categories grid (Plomberie, Électricité, Maçonnerie, Menuiserie, Peinture, Carrelage), nearby artisans map
- **Mission request**: text description + photo upload + AI-generated estimate card showing category, urgency level, and price range in FCFA
- **Artisan profile**: photo, name, trade, Score N'Zassa badge (0–100), star rating, completed missions count, reviews list
- **Devis (Quote) screen**: itemized quote showing labor vs materials cost, milestone list with dates and amounts
- **Mission tracking**: stepper showing phases (Devis → Financée → En cours → Jalons → Terminée), with photo proofs per milestone
- **OTP validation**: screen to enter 4-digit SMS code to validate a completed milestone
- **Rating screen**: 1–5 star rating + comment after mission completion

#### Artisan Screens
- **Dashboard**: active missions counter, wallet balance (Main d'œuvre wallet), notifications bell
- **New mission notification**: client name, work description, location distance, accept/decline buttons
- **Quote builder**: add labor lines and material lines, set milestones with amounts and target dates
- **J-Code generator**: screen showing large `PA-XXXX` code + QR code + USSD fallback code, for purchasing materials at approved suppliers
- **Milestone submission**: checklist + photo upload + GPS tag, submit button
- **Score N'Zassa**: circular progress showing 0–100 score with breakdown (Fiabilité, Intégrité, Qualité, Réactivité)

#### Fournisseur (Supplier) Screens
- **Scanner screen**: QR code scanner to validate J-Codes from artisans
- **Transaction confirmation**: show artisan name, materials amount in FCFA, GPS distance check indicator (green ✅ or red 🚨)
- **Payments list**: list of validated J-Code transactions with J+1 payout status

#### Shared Screens
- **Notifications center**: sorted by date, with icons per type (payment, validation, alert, litige)
- **Dispute / Litige screen**: button "Signaler un problème", description field + photo upload, dispute status tracker
- **Settings**: language, notifications, account info, logout

---

### 🔑 Key features to highlight in UI
- Escrow payment split shown as two progress bars: Wallet Matériaux (blue) vs Wallet Main d'œuvre (orange)
- Score N'Zassa badge always visible on artisan cards (colored: red < 40, orange 40–70, green > 70)
- GPS proximity warning on J-Code validation (must be within 100m of supplier)
- Milestone-based fund release with OTP confirmation
- For missions > 2,000,000 FCFA: show "Validation Référent requise" badge

---

### 💳 Payment methods shown in UI
- Wave CI (teal logo)
- Orange Money CI (orange logo)

---

Build all screens with realistic dummy data in French (French West African names, Abidjan neighborhoods like Cocody, Yopougon, Plateau, Adjamé).
