import glob, gzip, tarfile, os

pkgs = glob.glob(r'c:\pfsense\installer_source\packages\All\*.pkg')
for p in pkgs:
    try:
        with gzip.open(p, 'rb') as gz:
            with tarfile.open(fileobj=gz) as tar:
                for member in tar.getmembers():
                    if member.isfile():
                        f = tar.extractfile(member)
                        if f:
                            data = f.read()
                            if b'kernel is installed' in data:
                                print(f"MATCH: {os.path.basename(p)} -> {member.name}")
    except Exception as e:
        pass
print("Done.")
