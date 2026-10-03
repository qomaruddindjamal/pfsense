$files = Get-ChildItem "c:\pfsense\installer_source\packages\All\*.pkg"
foreach ($f in $files) {
    if ($f.Name -match "php") {
        Write-Host "Checking $($f.Name)"
        $res = & "C:\Program Files\7-Zip\7z.exe" l $f.FullName
        if ($res -match "php-fpm") {
            Write-Host ">>> MATCH: $($f.Name)"
        }
    }
}
