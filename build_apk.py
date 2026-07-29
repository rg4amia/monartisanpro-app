import os
import sys
import time
import shutil
import subprocess

print("Killing java, adb, dart, gradle, etc...")
os.system("taskkill /f /im java.exe 2>nul")
os.system("taskkill /f /im adb.exe 2>nul")
os.system("taskkill /f /im dart.exe 2>nul")

time.sleep(2)

print("Stopping Gradle daemons...")
os.chdir(r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\android")
subprocess.run([r".\gradlew.bat", "--stop"], shell=True)

# Force-remove lock directories using PowerShell
print("Forcibly removing build directory...")
subprocess.run(["powershell", "-Command", "Remove-Item -Recurse -Force -ErrorAction SilentlyContinue c:\\Users\\Utilisateur\\Documents\\GitHub\\monartisanpro-app\\frontend_flutter\\build"], shell=True)

print("Changing directory to frontend_flutter...")
os.chdir(r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter")

print("Running flutter build apk --split-per-abi...")
result = subprocess.run(["flutter", "build", "apk", "--split-per-abi"], capture_output=True, text=True, shell=True)

print("STDOUT:")
print(result.stdout)
print("STDERR:")
print(result.stderr)
print("Exit code:", result.returncode)

if result.returncode == 0:
    build_out_dir = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build\app\outputs\flutter-apk"
    print("Files in build output dir:")
    if os.path.exists(build_out_dir):
        for f in os.listdir(build_out_dir):
            print(" -", f)
            if f.endswith(".apk"):
                shutil.copy2(os.path.join(build_out_dir, f), os.path.join(r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app", f))
        print("Successfully copied split APKs to root workspace!")
    else:
        print("ERROR: Build output folder not found!")
else:
    print("ERROR: Flutter build failed!")


