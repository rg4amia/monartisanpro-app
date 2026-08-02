import os
import stat
import shutil

build_dir = r"c:\Users\Utilisateur\Documents\GitHub\monartisanpro-app\frontend_flutter\build"

def remove_readonly(func, path, excinfo):
    try:
        os.chmod(path, stat.S_IWRITE)
        func(path)
    except Exception as e:
        print(f"Failed to delete {path}: {e}")

if os.path.exists(build_dir):
    print(f"Attempting to delete {build_dir}...")
    shutil.rmtree(build_dir, onerror=remove_readonly)
else:
    print(f"{build_dir} does not exist.")

if os.path.exists(build_dir):
    print("Build directory still exists!")
    for root, dirs, files in os.walk(build_dir):
        print(f"Root: {root}")
        for d in dirs:
            print(f"  Dir: {d}")
        for f in files:
            print(f"  File: {f}")
else:
    print("Build directory successfully deleted!")
