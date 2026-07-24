import os

path = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build"
if os.path.exists(path):
    print("build contents:", os.listdir(path))
else:
    print("build path does not exist")
