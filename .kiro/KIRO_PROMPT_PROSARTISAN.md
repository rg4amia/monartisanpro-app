# Prompt Kiro — ProsArtisan (Spec-Driven)

## 🧾 Prompt principal à coller dans Kiro (Chat ou Spec)

---

Build **ProsArtisan**, a full-stack marketplace platform connecting clients, artisans (craftsmen), and hardware suppliers (quincailleries) in Côte d'Ivoire, West Africa.

The app is in **French**, uses **FCFA** as currency, and is built with **Laravel 11 (PHP)** for the backend API and **Flutter** for the mobile frontend. The database is **PostgreSQL with PostGIS** for geospatial queries.

---

### 🎯 Core business goal

ProsArtisan solves a trust problem in the informal artisan market by:
1. Verifying identity (KYC) before any transaction
2. Locking payments in escrow, split between materials and labor wallets
3. Releasing funds only after milestone validation via SMS OTP
4. Building a trust score (Score N'Zassa) that unlocks access to micro-credit

---

### 👥 User roles

- **Client** — requests work, pays, validates milestones
- **Artisan** — receives missions, generates material tokens (J-Codes), submits milestone proofs
- **Fournisseur** — approved hardware supplier, scans J-Codes to deliver materials
- **Référent de zone** — physically validates high-value missions (> 2,000,000 FCFA) and arbitrates disputes
- **Administrateur** — manages KYC, supplier approvals, dispute resolution, fraud alerts

---

### 📋 Requirements (generate specs for each)

#### REQ-01 — Authentication & KYC
- Phone number + OTP login
- Role selection on signup (Client / Artisan / Fournisseur)
- KYC flow: upload CNI photo + selfie liveness check
- Admin validates KYC manually; profile status = `actif` after approval
- Rejected profiles receive correction requests and can resubmit

#### REQ-02 — Mission Request & AI Matching
- Client describes need in free text + photos
- Call **Google Gemini API** to analyze and return: work category, urgency level, estimated price range in FCFA
- Use **PostGIS** to search artisans within 2 km radius
- Artisan positions are blurred to 50 m precision for privacy
- Prioritized artisans shown with "marqueur doré" (golden badge) status
- Client views artisan profile: photo, trade, Score N'Zassa (0–100), rating, completed missions

#### REQ-03 — Quote (Devis) System
- Artisan creates itemized quote: labor lines vs material lines
- Quote includes milestones with amounts and target dates
- Client can accept, reject, or negotiate the quote

#### REQ-04 — Escrow Payment & Wallet Fragmentation
- Client pays deposit via **Wave CI** or **Orange Money CI**
- On payment confirmation: auto-split into two wallets:
  - `wallet_materiaux` (e.g. 65%) — locked for supplier payout
  - `wallet_main_oeuvre` (e.g. 35%) — released milestone by milestone
- Mission status transitions: `en_attente` → `financee` → `en_cours` → `terminee`
- Artisan notified when mission is funded

#### REQ-05 — J-Code Material Token System
- Artisan generates a **J-Code** (`PA-XXXX` format + QR code + USSD fallback)
- Artisan travels to nearest approved supplier
- Supplier scans QR or enters USSD code
- **GPS check**: supplier device must be within 100 m of artisan for transaction to validate
- If GPS check fails: transaction blocked + automatic admin alert
- On success: materials delivered, supplier receives J+1 bank transfer confirmation
- Artisan uploads geotagged photo of materials on site → client notified

#### REQ-06 — Milestone Validation (Jalons)
- Artisan submits completed milestone with geotagged photos
- System sends **SMS OTP** to client for validation
- Client enters OTP → milestone validated → labor wallet released to artisan via Mobile Money
- For missions > **2,000,000 FCFA**: physical validation by Référent de zone required before release
- Cycle repeats for each milestone

#### REQ-07 — Mission Closure & Score N'Zassa
- Artisan submits intervention report (checklist + summary)
- Client signs digitally (finger or OTP SMS)
- Client rates artisan (1–5 stars)
- **Score N'Zassa** calculated:
  - Fiabilité (Reliability): 40%
  - Intégrité (Integrity): 30%
  - Qualité (Quality): 20%
  - Réactivité (Responsiveness): 10%
- Score archived for banking audit
- If score > 70/100 → artisan eligible for **emergency micro-credit** (disbursed in < 2h)
- PDF solvency report generated for partner microfinance institutions

#### REQ-08 — Dispute (Litige) Management
- Client or artisan can trigger a dispute at any time during a mission
- Admin reviews logs, photos, chat history
- Arbitration decisions:
  - Client is right → refund client
  - Artisan is right → pay artisan
  - Uncertain → freeze funds + send Référent for physical visit

---

### 🗄️ Key database entities

```sql
users (id, phone, role, kyc_status, score_nzassa, wallet_materiaux, wallet_mo, geom GEOGRAPHY)
missions (id, client_id, artisan_id, status, montant_total, montant_materiaux, montant_mo)
devis (id, mission_id, lignes_json, jalons_json, statut)
jalons (id, mission_id, ordre, montant, statut, otp_code, photos_json)
jcodes (id, mission_id, artisan_id, fournisseur_id, code, montant, statut, geom_scan GEOGRAPHY)
transactions (id, type, montant, wallet_source, wallet_dest, provider, statut)
litiges (id, mission_id, declencheur_id, type, statut, decision)
evaluations (id, mission_id, evaluateur_id, evalue_id, note, commentaire)
fournisseurs_agrees (id, user_id, nom_boutique, geom GEOGRAPHY, statut)
```

---

### 🔧 Technical constraints

- Laravel 11 REST API, sanctum auth, queued jobs for notifications
- PostGIS for all geospatial queries (ST_DWithin, ST_Distance)
- Positions blurred using ST_Buffer + random offset before returning to client
- SMS via infobip or Twilio (francophone West Africa coverage)
- Mobile Money webhooks for payment confirmation (Wave CI, Orange Money CI)
- PDF generation with Laravel Snappy or DomPDF (solvency reports)
- All monetary amounts stored as integers (FCFA, no decimals)
- All user-facing text in French

---

### 📎 Reference

The complete business flow diagram is attached as `prosartisan_flux.mmd` (Mermaid format). Use it to understand state transitions, actor interactions, and decision branches before generating the architecture and task plan.
