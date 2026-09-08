import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Clé Yandex MapKit : jamais commitée. Ordre de résolution :
//   1. variable d'environnement YANDEX_MAPKIT_API_KEY (CI)
//   2. android/local.properties → yandex.mapkit.apiKey (poste dev)
//   3. chaîne vide (la carte ne s'affichera pas, build non bloqué)
val yandexMapKitApiKey: String = run {
    System.getenv("YANDEX_MAPKIT_API_KEY")?.takeIf { it.isNotBlank() }?.let { return@run it }
    val props = Properties()
    val f = rootProject.file("local.properties")
    if (f.exists()) {
        f.inputStream().use { props.load(it) }
    }
    props.getProperty("yandex.mapkit.apiKey", "")
}

android {
    namespace = "com.prosartisan.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.prosartisan.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = 26  // Required by yandex_maps_mapkit
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Injecté dans AndroidManifest.xml (<meta-data com.yandex.maps.api_key>)
        manifestPlaceholders["YANDEX_MAPKIT_API_KEY"] = yandexMapKitApiKey
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")

            // Rétrécissement : supprime le code et les ressources inutilisés
            // (~30-40 % de moins sur le .dex + assets). Règles dans proguard-rules.pro.
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
    }

    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
        // .so compressés dans l'APK → téléchargement plus léger.
        jniLibs {
            useLegacyPackaging = true
        }
    }
}

flutter {
    source = "../.."
}
