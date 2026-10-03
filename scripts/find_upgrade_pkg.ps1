$files = Get-ChildItem "c:\pfsense\installer_source\packages\All\*.pkg"
foreach ($f in $files) {
    $res = & "C:\Program Files\7-Zip\7z.exe" e -so $f.FullName "+MANIFEST" 2>$null
    if ($res -match "pfSense-upgrade") {
        Write-Host "Found pfSense-upgrade in $($f.Name)"
    }
}
