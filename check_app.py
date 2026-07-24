import os

path = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build\app"
if os.path.exists(path):
    print("app contents:", os.listdir(path))
else:
    print("app path does not exist")
