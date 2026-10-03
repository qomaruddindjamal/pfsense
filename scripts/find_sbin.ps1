$files = Get-ChildItem "c:\pfsense\installer_source\packages\All\*.pkg"
foreach ($f in $files) {
    if ($f.Name -match "php|nginx|web|fcgi") {
        $p = & "C:\Program Files\7-Zip\7z.exe" e -so $f.FullName | & "C:\Program Files\7-Zip\7z.exe" l -si -ttar
        if ($p -match "php-fpm") {
            Write-Host ">>> FOUND php-fpm in $($f.Name)"
        }
        if ($p -match "nginx") {
            Write-Host ">>> FOUND nginx in $($f.Name)"
        }
    }
}
