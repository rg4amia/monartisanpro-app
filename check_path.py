import os

path = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build\app\outputs\flutter-apk\app-release.apk"
print("Exists:", os.path.exists(path))
if os.path.exists(path):
    print("Size:", os.path.getsize(path))
