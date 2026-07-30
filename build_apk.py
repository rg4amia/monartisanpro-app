import os
import sys
import time
import shutil
import subprocess

PROJECT_ROOT = os.path.dirname(os.path.abspath(__file__))
FLUTTER_DIR = os.path.join(PROJECT_ROOT, "frontend_flutter")
ANDROID_DIR = os.path.join(FLUTTER_DIR, "android")
BUILD_DIR = os.path.join(FLUTTER_DIR, "build")
GRADLEW = os.path.join(ANDROID_DIR, "gradlew.bat")

print("=== MonArtisanPro - Automated Reliable Build ===")

# 1. Nettoyage complet des processus verrouillants sous Windows
print("\n[1/4] Arret des processus Java, Gradle, Dart et Android Studio...")
os.system("taskkill /f /im java.exe 2>nul")
os.system("taskkill /f /im javaw.exe 2>nul")
os.system("taskkill /f /im adb.exe 2>nul")
os.system("taskkill /f /im dart.exe 2>nul")
time.sleep(2)

# 2. Suppression forcée du dossier build
print("\n[2/4] Nettoyage du repertoire de build...")
if os.path.exists(BUILD_DIR):
    subprocess.run(["powershell", "-Command", f"Remove-Item -Recurse -Force -ErrorAction SilentlyContinue '{BUILD_DIR}'"], shell=True)

# 3. Compilation directe via Gradle sans daemon et avec contournement des nettoyages concurrents
print("\n[3/4] Compilation directe via Gradle...")
os.chdir(ANDROID_DIR)

gradle_cmd = [
    GRADLEW,
    "assembleRelease",
    "-x", "verifyReleaseResources",
    "--no-daemon",
    "--offline",
    "--max-workers=2"
]

# Si le premier essai hors-ligne/rapide échoue, tenter sans --offline
result = subprocess.run(gradle_cmd, shell=True)

if result.returncode != 0:
    print("\n[!] Deuxieme tentative sans le mode offline...")
    gradle_cmd_online = [
        GRADLEW,
        "assembleRelease",
        "-x", "verifyReleaseResources",
        "--no-daemon",
        "--max-workers=2"
    ]
    result = subprocess.run(gradle_cmd_online, shell=True)

if result.returncode != 0:
    print("\n[!] Erreur : La compilation Gradle a echoue.")
    sys.exit(1)

# 4. Copie de l'APK vers la racine du projet
print("\n[4/4] Copie et organisation des APKs generes...")
build_out_dir = os.path.join(FLUTTER_DIR, "build", "app", "outputs", "flutter-apk")
release_apk = os.path.join(build_out_dir, "app-release.apk")

if os.path.exists(release_apk):
    target_apk = os.path.join(PROJECT_ROOT, "prosartisan-production.apk")
    shutil.copy2(release_apk, target_apk)
    size_mb = os.path.getsize(target_apk) / (1024 * 1024)
    print(f"\n[OK] SUCCES ! L'APK de production a ete genere avec succes :")
    print(f" -> Fichier : {target_apk}")
    print(f" -> Taille  : {size_mb:.2f} MB")
else:
    print("\n[!] Erreur : Fichier APK introuvable.")
    sys.exit(1)
