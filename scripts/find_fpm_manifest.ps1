$files = Get-ChildItem "c:\pfsense\installer_source\packages\All\*.pkg"
foreach ($f in $files) {
    $p = & "C:\Program Files\7-Zip\7z.exe" e -so $f.FullName "+COMPACT_MANIFEST" 2>$null
    if ($p -match "php-fpm") {
        Write-Host ">>> FOUND php-fpm in $($f.Name)"
    }
}
