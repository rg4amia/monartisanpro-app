import os
import sys
import time
import shutil
import subprocess

PROJECT_ROOT = os.path.dirname(os.path.abspath(__file__))
FLUTTER_DIR = os.path.join(PROJECT_ROOT, "frontend_flutter")
ANDROID_DIR = os.path.join(FLUTTER_DIR, "android")
BUILD_DIR = os.path.join(FLUTTER_DIR, "build")

print("=== MonArtisanPro - Clean Reliable Flutter Build ===")

# 1. Nettoyage des processus
print("\n[1/5] Arret des processus en arriere-plan...")
os.system("taskkill /f /im java.exe 2>nul")
os.system("taskkill /f /im javaw.exe 2>nul")
os.system("taskkill /f /im adb.exe 2>nul")
os.system("taskkill /f /im dart.exe 2>nul")
time.sleep(1)

# Stop gradle daemons to release file locks
print("Arret des Gradle Daemons...")
android_dir = os.path.join(FLUTTER_DIR, "android")
if os.path.exists(android_dir):
    subprocess.run("gradlew --stop", shell=True, cwd=android_dir)

# Forcibly delete ephemeral and build dirs using cmd rmdir to bypass MAX_PATH and locking
print("Nettoyage force des repertoires temporaires...")
dirs_to_clean = [
    os.path.join(FLUTTER_DIR, "build"),
    os.path.join(FLUTTER_DIR, ".dart_tool"),
    os.path.join(FLUTTER_DIR, "android", "app", "build"),
    os.path.join(FLUTTER_DIR, "android", ".gradle"),
    os.path.join(FLUTTER_DIR, "ios", "Flutter", "ephemeral"),
    os.path.join(FLUTTER_DIR, "linux", "flutter", "ephemeral"),
    os.path.join(FLUTTER_DIR, "macos", "Flutter", "ephemeral"),
    os.path.join(FLUTTER_DIR, "windows", "flutter", "ephemeral"),
]
for d in dirs_to_clean:
    if os.path.exists(d):
        os.system(f'cmd /c "rmdir /s /q \"{d}\""')

# 2. Nettoyage du cache Flutter Kernel corrompu
print("\n[2/5] Nettoyage Flutter Clean & Re-fetch...")
os.chdir(FLUTTER_DIR)
subprocess.run("flutter clean", shell=True)
subprocess.run("flutter pub get", shell=True)

# 3. Compilation officielle via Flutter Tool (regénère proprement le Kernel AOT)
print("\n[3/5] Compilation Flutter APK Release...")
flutter_build_cmd = "flutter build apk --release --split-per-abi --no-tree-shake-icons"
result = subprocess.run(flutter_build_cmd, shell=True)

if result.returncode != 0:
    print("\n[!] Erreur lors du build Flutter.")
    sys.exit(1)

# 4. Copie des APKs générés vers la racine du projet
print("\n[4/5] Copie des APKs generes...")
build_out_dir = os.path.join(FLUTTER_DIR, "android", "app", "build", "outputs", "flutter-apk")

copied_any = False
if os.path.exists(build_out_dir):
    for filename in os.listdir(build_out_dir):
        if filename.endswith(".apk") and "release" in filename:
            source_path = os.path.join(build_out_dir, filename)
            target_path = os.path.join(PROJECT_ROOT, filename)
            shutil.copy2(source_path, target_path)
            size_mb = os.path.getsize(target_path) / (1024 * 1024)
            print(f" -> Copie réussie : {target_path} ({size_mb:.2f} MB)")
            copied_any = True

if not copied_any:
    print("\n[!] Erreur : Aucun fichier APK généré trouvé.")
    sys.exit(1)
