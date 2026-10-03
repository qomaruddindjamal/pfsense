$files = Get-ChildItem "c:\pfsense\installer_source\packages\All\*.pkg"
foreach ($f in $files) {
    # Check if the pkg contains the string by extracting and piping to grep/Select-String
    $out = & "C:\Program Files\7-Zip\7z.exe" e -so $f.FullName 2>$null | & "C:\Program Files\7-Zip\7z.exe" e -si -ttar -so 2>$null | Select-String "identify which pfSense kernel"
    if ($out) {
        Write-Host "FOUND IN: $($f.Name)"
        break
    }
}
Write-Host "Scan completed."
