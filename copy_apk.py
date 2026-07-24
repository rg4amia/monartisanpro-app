import os
import shutil

for root, dirs, files in os.walk("."):
    for file in files:
        if file == "app-release.apk":
            src = os.path.join(root, file)
            dst = "prosartisan-release.apk"
            print(f"Found APK at {src}. Copying to {dst}...")
            shutil.copy2(src, dst)
            print("Copy complete.")
            break
