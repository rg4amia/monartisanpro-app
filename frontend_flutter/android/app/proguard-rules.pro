# ─────────────────────────────────────────────────────────────────────────────
# Règles R8 / ProGuard — build release rétréci (isMinifyEnabled + shrinkResources)
# ─────────────────────────────────────────────────────────────────────────────

# Flutter embarque déjà ses propres règles ; on ne garde ici que ce qui casse
# au shrink : SDK natifs à forte réflexion / JNI.

# ── Yandex MapKit (SDK natif, bindings JNI + réflexion interne) ───────────────
-keep class com.yandex.** { *; }
-keep interface com.yandex.** { *; }
-dontwarn com.yandex.**

# ── OneSignal ────────────────────────────────────────────────────────────────
-keep class com.onesignal.** { *; }
-dontwarn com.onesignal.**

# ── geolocator / permission_handler / mobile_scanner (services + callbacks) ──
-keep class com.baseflow.** { *; }
-dontwarn com.baseflow.**

# ── flutter_secure_storage (Tink / androidx.security) ────────────────────────
-keep class androidx.security.crypto.** { *; }
-dontwarn com.google.crypto.tink.**

# ── Modèles JSON (json_serializable génère du code, pas de réflexion) ────────
# Rien à garder : les *.g.dart sont du code Dart AOT, hors périmètre R8.

# ── Divers : ne pas avertir sur les annotations manquantes ──────────────────
-dontwarn javax.annotation.**
-dontwarn org.conscrypt.**
