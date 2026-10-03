import gzip, tarfile

pkg = r'c:\pfsense\installer_source\packages\All\pfSense-upgrade-1.3.40.pkg'
with gzip.open(pkg, 'rb') as gz:
    with tarfile.open(fileobj=gz) as tar:
        f = tar.extractfile('/usr/local/libexec/pfSense-upgrade')
        content = f.read().decode('utf-8', errors='ignore')
        lines = content.splitlines()
        for l in lines[180:250]:
            print(l)
