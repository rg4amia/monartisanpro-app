import os
import shutil

# Emplacement de référence des APK livrables (sortie standard de Flutter, ignorée par git).
APK_OUTPUT_DIR = os.path.join("frontend_flutter", "build", "app", "outputs", "flutter-apk")

for root, dirs, files in os.walk("."):
    for file in files:
        if file == "app-release.apk":
            src = os.path.join(root, file)
            dst = os.path.join(APK_OUTPUT_DIR, "prosartisan-release.apk")
            print(f"Found APK at {src}. Copying to {dst}...")
            shutil.copy2(src, dst)
            print("Copy complete.")
            break
