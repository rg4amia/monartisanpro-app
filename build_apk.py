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

# Clean up existing APK files at the root of the project and build_pa to avoid stale files
print("Nettoyage des APKs existants a la racine du projet...")
for f in os.listdir(PROJECT_ROOT):
    if f.endswith(".apk"):
        try:
            os.remove(os.path.join(PROJECT_ROOT, f))
            print(f" -> Supprime : {f}")
        except Exception as e:
            print(f" [!] Impossible de supprimer {f} : {e}")

build_pa_apk_dir = r"C:\Users\Utilisateur\build_pa\app\outputs\flutter-apk"
if os.path.exists(build_pa_apk_dir):
    print(f"Nettoyage des APKs existants dans {build_pa_apk_dir}...")
    for f in os.listdir(build_pa_apk_dir):
        if f.endswith(".apk"):
            try:
                os.remove(os.path.join(build_pa_apk_dir, f))
                print(f" -> Supprime : {f}")
            except Exception as e:
                print(f" [!] Impossible de supprimer {f} dans build_pa : {e}")

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
    r"C:\Users\Utilisateur\build_pa",
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
flutter_build_cmd = "flutter build apk --release --no-tree-shake-icons"
result = subprocess.run(flutter_build_cmd, shell=True)

# 4. Copie des APKs générés vers la racine du projet
print("\n[4/5] Copie des APKs generes...")
build_out_dirs = [
    os.path.join(FLUTTER_DIR, "android", "app", "build", "outputs", "flutter-apk"),
    r"C:\Users\Utilisateur\build_pa\app\outputs\flutter-apk"
]

copied_any = False
for build_out_dir in build_out_dirs:
    if os.path.exists(build_out_dir):
        print(f"Recherche d'APKs dans : {build_out_dir}")
        for filename in os.listdir(build_out_dir):
            if filename.endswith(".apk") and "release" in filename:
                source_path = os.path.join(build_out_dir, filename)
                target_paths = [
                    os.path.join(PROJECT_ROOT, "prosartisan-app.apk"),
                    os.path.join(r"C:\Users\Utilisateur\build_pa\app\outputs\flutter-apk\prosartisan-app.apk")
                ]
                for target_path in target_paths:
                    target_dir = os.path.dirname(target_path)
                    try:
                        os.makedirs(target_dir, exist_ok=True)
                        shutil.copy2(source_path, target_path)
                        size_mb = os.path.getsize(target_path) / (1024 * 1024)
                        print(f" -> Copie réussie : {target_path} ({size_mb:.2f} MB)")
                        copied_any = True
                    except Exception as e:
                        print(f" [!] Echec de copie pour {filename} vers {target_path} : {e}")

if not copied_any:
    print("\n[!] Erreur : Aucun fichier APK généré trouvé.")
    sys.exit(1)
else:
    # Delete app-release.apk if it exists to avoid user downloading cached version
    for p in [os.path.join(PROJECT_ROOT, "app-release.apk"), os.path.join(r"C:\Users\Utilisateur\build_pa\app\outputs\flutter-apk", "app-release.apk")]:
        if os.path.exists(p):
            try:
                os.remove(p)
                print(f" -> Supprime (nettoyage cache) : {p}")
            except Exception as e:
                print(f" [!] Impossible de supprimer {p} : {e}")
    print("\n[+] Build et copie terminés avec succès !")
