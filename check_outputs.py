import os

path = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build\app\outputs"
if os.path.exists(path):
    print("outputs contents:", os.listdir(path))
    sub = os.path.join(path, "flutter-apk")
    if os.path.exists(sub):
        print("flutter-apk contents:", os.listdir(sub))
else:
    print("outputs path does not exist")
