import os
import sys
import time
import shutil
import subprocess

print("Killing java, adb, dart, gradle, etc...")
os.system("taskkill /f /im java.exe 2>nul")
os.system("taskkill /f /im adb.exe 2>nul")

time.sleep(2)

build_dir = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build"
if os.path.exists(build_dir):
    print("Attempting to delete build folder...")
    for i in range(5):
        try:
            shutil.rmtree(build_dir)
            print("Successfully deleted build folder.")
            break
        except Exception as e:
            print(f"Delete failed (attempt {i+1}): {e}. Retrying in 2s...")
            os.system("taskkill /f /im java.exe 2>nul")
            os.system("taskkill /f /im adb.exe 2>nul")
            time.sleep(2)

print("Running flutter build apk...")
os.chdir(r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter")
result = subprocess.run(["flutter", "build", "apk", "--release"], capture_output=True, text=True)

print("STDOUT:")
print(result.stdout)
print("STDERR:")
print(result.stderr)
print("Exit code:", result.returncode)

if result.returncode == 0:
    src = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build\app\outputs\flutter-apk\app-release.apk"
    dst = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\prosartisan-release.apk"
    if os.path.exists(src):
        shutil.copy2(src, dst)
        print("Successfully copied new APK to root workspace!")
    else:
        print("ERROR: APK built successfully but file not found at expected path:", src)
else:
    print("ERROR: Flutter build failed!")
