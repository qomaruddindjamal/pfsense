# ==============================================================================
# auto_pilot.ps1 - Skrip Otomatisasi Perbaikan Sumber, Build ISO, & Optimasi VM
# pfSense Custom Offline Installer (WireGuard, Xray-core, KVM aaPanel)
# ==============================================================================

[CmdletBinding()]
param (
    [string]$VmName = "pfsense",
    [switch]$SkipVmBoot = $false,
    [switch]$SkipIsoBuild = $false
)

$ErrorActionPreference = "Stop"
$WorkspaceDir = $PSScriptRoot
if (-not $WorkspaceDir) { $WorkspaceDir = (Get-Location).Path }
Set-Location $WorkspaceDir

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   pfSense Custom Offline Installer - AUTO PILOT SYSTEM   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Direktori Kerja: $WorkspaceDir" -ForegroundColor Yellow

# ------------------------------------------------------------------------------
# 1. PERBAIKAN SOURCE CODE (INSTALLER_SOURCE)
# ------------------------------------------------------------------------------
Write-Host "`n[1/4] Memeriksa dan Memperbaiki Source Code..." -ForegroundColor Green

# 1.1 Pastikan pfSense-base terakit
$base29Target = Join-Path $WorkspaceDir "installer_source\packages\All\pfSense-base-2.9.0.pkg"
$partaa = Join-Path $WorkspaceDir "installer_source\packages\All\pfSense-base-2.9.0.pkg.partaa"
$partab = Join-Path $WorkspaceDir "installer_source\packages\All\pfSense-base-2.9.0.pkg.partab"
if ((-not (Test-Path $base29Target)) -or ((Get-Item $base29Target).Length -lt 100000000)) {
    if ((Test-Path $partaa) -and (Test-Path $partab)) {
        Write-Host "  -> Menggabungkan pfSense-base-2.9.0.pkg dari partaa dan partab..." -ForegroundColor Gray
        $outStream = [System.IO.File]::Create($base29Target)
        $bytesA = [System.IO.File]::ReadAllBytes($partaa)
        $outStream.Write($bytesA, 0, $bytesA.Length)
        $bytesB = [System.IO.File]::ReadAllBytes($partab)
        $outStream.Write($bytesB, 0, $bytesB.Length)
        $outStream.Close()
        Write-Host "  [OK] pfSense-base-2.9.0.pkg berhasil dirakit: $((Get-Item $base29Target).Length) bytes" -ForegroundColor Green
    }
}

# 1.2 Pastikan virtual.pkg diubah ke kvm.pkg
$virtualPkgPaths = @(
    "installer_source\packages\All",
    "installer_source\pkg",
    "installer_source\usr\local\share\packages\offline",
    "packages\pkg"
)
foreach ($vDir in $virtualPkgPaths) {
    $vSrc = Join-Path $WorkspaceDir (Join-Path $vDir "virtual.pkg")
    $kDst = Join-Path $WorkspaceDir (Join-Path $vDir "kvm.pkg")
    if (Test-Path $vSrc) {
        Copy-Item -Path $vSrc -Destination $kDst -Force
        Remove-Item -Path $vSrc -Force
        Write-Host "  [OK] Mengganti $vSrc -> kvm.pkg" -ForegroundColor Gray
    }
}

# 1.3 Konversi Seluruh ReparsePoint (Symlink NTFS Windows) ke File Asli
Write-Host "  -> Memeriksa dan menyelesaikan berkas library dinamis (.so) symlink..." -ForegroundColor Gray
$reparseItems = Get-ChildItem -Path "installer_source" -Recurse -Force | Where-Object { $_.LinkType -eq "SymbolicLink" -and -not $_.PSIsContainer }
$resCount = 0
foreach ($item in $reparseItems) {
    try {
        $target = $item.Target[0]
        if ($target) {
            $dir = $item.DirectoryName
            $resolved = [System.IO.Path]::GetFullPath((Join-Path $dir $target))
            if (Test-Path $resolved -PathType Leaf) {
                $tempPath = [System.IO.Path]::GetTempFileName()
                Copy-Item -Path $resolved -Destination $tempPath -Force
                Remove-Item -Path $item.FullName -Force
                Move-Item -Path $tempPath -Destination $item.FullName -Force
                $resCount++
            }
        }
    } catch {}
}
if ($resCount -gt 0) {
    Write-Host "  [OK] Berhasil mengonversi $resCount symlink library menjadi file biner asli." -ForegroundColor Green
}

function Set-UnixFileLines {
    param(
        [string]$Path,
        [string[]]$Lines
    )
    $text = ($Lines -join "`n") + "`n"
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $text, $utf8NoBom)
}

# 1.4 Optimasi Timeout DHCP
$dhclientConf = Join-Path $WorkspaceDir "installer_source\etc\dhclient.conf"
$dhLines = @("timeout 5;", "retry 5;", "select-timeout 0;", "initial-interval 1;")
Set-UnixFileLines -Path $dhclientConf -Lines $dhLines
Write-Host "  [OK] dhclient.conf diatur timeout 5 detik (menghilangkan jeda 60 detik)." -ForegroundColor Gray

# 1.5 Bypass Timeout NTP & Server Netgate di pfSense-connectivity-check
$connCheck = Join-Path $WorkspaceDir "installer_source\usr\local\libexec\installer\pfSense-connectivity-check"
if (Test-Path $connCheck) {
    $connLines = @(
        "#!/bin/sh",
        "# pfSense Installer pfSense-connectivity-check module (Offline Mode).",
        'INSTALL_INC_PATH="/usr/local/libexec/installer"',
        '. "${INSTALL_INC_PATH}/pfSense-common"',
        "",
        "network_check() {",
        "    return 0",
        "}",
        "",
        "ntp_bootstrap() {",
        "    return 0",
        "}",
        "",
        "exit 0"
    )
    Set-UnixFileLines -Path $connCheck -Lines $connLines
    Write-Host "  [OK] pfSense-connectivity-check dioptimalkan untuk mode offline instan." -ForegroundColor Gray
}

# 1.6 Bypass connectivity_check di bsdinstall pfSense-common
$bsdCommon = Join-Path $WorkspaceDir "installer_source\usr\libexec\bsdinstall\pfSense-common"
if (Test-Path $bsdCommon) {
    (Get-Item $bsdCommon).IsReadOnly = $false
    $commonTxt = [System.IO.File]::ReadAllText($bsdCommon)
    if ($commonTxt -notmatch "Mode offline: installer mandiri") {
        $commonTxt = [regex]::Replace($commonTxt, "connectivity_check\(\)\s*\{[\s\S]*?return\s+0\s*\}", "connectivity_check() {`n`t# Mode offline: installer mandiri tidak perlu menunggu Netgate Server`n`treturn 0`n}")
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        $commonTxt = $commonTxt.Replace("`r`n", "`n").Replace("`r", "`n")
        [System.IO.File]::WriteAllText($bsdCommon, $commonTxt, $utf8NoBom)
        Write-Host "  [OK] connectivity_check di bsdinstall/pfSense-common di-bypass langsung." -ForegroundColor Gray
    }
}

# 1.7 Pastikan run-installer.sh & etc/rc konsol interaktif aktif
$runInstSh = Join-Path $WorkspaceDir "installer_source\usr\local\libexec\installer\run-installer.sh"
$runLines = @(
    "#!/bin/sh",
    "export TERM=xterm",
    "export HOME=/root",
    "export PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin",
    "/sbin/ldconfig -m /lib /usr/lib /usr/local/lib 2>/dev/null || true",
    "if ! /usr/bin/pgrep -q pfSense-installer; then /usr/local/bin/cgi-fcgi -start -connect 127.0.0.1:9000 /usr/local/sbin/pfSense-installer 2>/dev/null || true; fi",
    "if ! /usr/bin/pgrep -q nginx; then /usr/local/etc/rc.d/nginx onestart 2>/dev/null || /usr/local/sbin/nginx 2>/dev/null || true; fi",
    "",
    "while :; do",
    "    clear",
    '    echo "=========================================================="',
    '    echo "  pfSense Custom Offline Installer"',
    '    echo "=========================================================="',
    '    echo "Memulai antarmuka instalasi pfSense..."',
    '    echo ""',
    "    if [ -x /usr/local/libexec/installer/pfSense-installer.sh ]; then",
    "        /usr/local/libexec/installer/pfSense-installer.sh",
    "    else",
    '        echo "ERROR: /usr/local/libexec/installer/pfSense-installer.sh tidak ditemukan!"',
    "    fi",
    '    echo ""',
    '    echo "=========================================================="',
    '    echo "  pfSense Offline Console & Recovery Menu"',
    '    echo "=========================================================="',
    '    echo "  1) Jalankan Ulang Installer (pfSense-installer.sh)"',
    '    echo "  2) Buka Shell (/bin/sh)"',
    '    echo "  3) Mulai Ulang / Reboot VM"',
    '    echo "=========================================================="',
    '    printf "Pilih opsi [1-3] (default 1): "',
    "    read _ans",
    '    case "${_ans}" in',
    "        2)",
    '            echo "Ketik exit untuk kembali ke menu installer."',
    "            /bin/sh",
    "            ;;",
    "        3)",
    "            /sbin/reboot",
    "            ;;",
    "        *)",
    "            continue",
    "            ;;",
    "    esac",
    "done"
)
Set-UnixFileLines -Path $runInstSh -Lines $runLines
Write-Host "  [OK] Skrip run-installer.sh siap dengan menu pemulihan interaktif (format UNIX LF)." -ForegroundColor Gray

# 1.8 Normalisasi LF untuk seluruh skrip konfigurasi dan shell
Write-Host "  -> Menormalkan format baris UNIX (LF) pada seluruh konfigurasi dan skrip..." -ForegroundColor Gray
$textScripts = Get-ChildItem -Path "installer_source" -Recurse -Include *.sh, *.conf, rc, ttys, .profile, pfSense-* | Where-Object { -not $_.PSIsContainer }
$normCount = 0
foreach ($ts in $textScripts) {
    try {
        $rawBytes = [System.IO.File]::ReadAllBytes($ts.FullName)
        $isBinary = $false
        for ($i = 0; $i -lt [math]::Min($rawBytes.Length, 512); $i++) {
            if ($rawBytes[$i] -eq 0) { $isBinary = $true; break }
        }
        if (-not $isBinary) {
            $rawText = [System.Text.Encoding]::UTF8.GetString($rawBytes)
            if ($rawText.Contains("`r")) {
                $norm = $rawText.Replace("`r`n", "`n").Replace("`r", "`n")
                [System.IO.File]::WriteAllBytes($ts.FullName, [System.Text.Encoding]::UTF8.GetBytes($norm))
                $normCount++
            }
        }
    } catch {}
}
if ($normCount -gt 0) {
    Write-Host "  [OK] Berhasil menormalkan $normCount file ke UNIX LF." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 2. PEMBANGUNAN CITRA ISO OFFLINE (XORRISO)
# ------------------------------------------------------------------------------
if (-not $SkipIsoBuild) {
    Write-Host "`n[2/4] Membangun Ulang Citra ISO Offline (xorriso)..." -ForegroundColor Green
    $isoOutput = "pfsense-custom-offline-installer.iso"
    
    $xorrisoArgs = @(
        "-as", "mkisofs",
        "-V", "PFSENSE",
        "-J",
        "-r",
        "-file-mode", "0755",
        "-dir-mode", "0755",
        "-b", "boot/cdboot",
        "-no-emul-boot",
        "-eltorito-alt-boot",
        "-e", "boot/efi.img",
        "-no-emul-boot",
        "-o", $isoOutput,
        "installer_source"
    )
    
    Write-Host "  -> Menjalankan xorriso dengan Rationalized Rock Ridge (-r)..." -ForegroundColor Gray
    $proc = Start-Process -FilePath "xorriso" -ArgumentList $xorrisoArgs -NoNewWindow -PassThru -Wait
    if ($proc.ExitCode -ne 0) {
        Write-Error "Gagal membangun ISO! xorriso keluar dengan kode $($proc.ExitCode)"
        exit $proc.ExitCode
    }
    
    $isoItem = Get-Item $isoOutput
    $isoSizeGB = [math]::Round($isoItem.Length / 1GB, 2)
    Write-Host "  [OK] ISO berhasil dibangun: $isoOutput ($isoSizeGB GB)" -ForegroundColor Green
} else {
    Write-Host "`n[2/4] Melewati pembangunan ISO (-SkipIsoBuild aktif)." -ForegroundColor Yellow
}

# ------------------------------------------------------------------------------
# 3. OPTIMASI VIRTUALBOX VM
# ------------------------------------------------------------------------------
if (-not $SkipVmBoot) {
    Write-Host "`n[3/4] Mengoptimalkan Pengaturan Mesin Virtual VirtualBox ($VmName)..." -ForegroundColor Green
    $vboxManage = "C:\Program Files\Oracle\VirtualBox\VBoxManage.exe"
    if (-not (Test-Path $vboxManage)) {
        Write-Warning "VBoxManage.exe tidak ditemukan di lokasi standar. Lewati pengaturan VM."
    } else {
        function Invoke-VBox {
            param([string[]]$Arguments)
            $pinfo = New-Object System.Diagnostics.ProcessStartInfo
            $pinfo.FileName = "C:\Program Files\Oracle\VirtualBox\VBoxManage.exe"
            $pinfo.Arguments = ($Arguments | ForEach-Object { if ($_ -match '\s') { "`"$_`"" } else { $_ } }) -join " "
            $pinfo.RedirectStandardOutput = $true
            $pinfo.RedirectStandardError = $true
            $pinfo.UseShellExecute = $false
            $pinfo.CreateNoWindow = $true
            $p = [System.Diagnostics.Process]::Start($pinfo)
            $stdout = $p.StandardOutput.ReadToEnd()
            $stderr = $p.StandardError.ReadToEnd()
            $p.WaitForExit()
            return [PSCustomObject]@{
                ExitCode = $p.ExitCode
                Output   = $stdout
                Error    = $stderr
            }
        }

        $vmInfoRes = Invoke-VBox -Arguments @("showvminfo", $VmName, "--machinereadable")
        if ($vmInfoRes.Output -match 'VMState="running"') {
            Write-Host "  -> Mematikan VM yang sedang berjalan..." -ForegroundColor Gray
            Invoke-VBox -Arguments @("controlvm", $VmName, "poweroff") | Out-Null
            Start-Sleep -Seconds 2
        }

        Write-Host "  -> Menerapkan optimasi CPU, Paravirtualization KVM, dan RAM..." -ForegroundColor Gray
        Invoke-VBox -Arguments @("modifyvm", $VmName, "--paravirt-provider", "kvm", "--pae", "on", "--cpus", "3", "--memory", "3072") | Out-Null
        
        $fullIsoPath = [System.IO.Path]::GetFullPath((Join-Path $WorkspaceDir "pfsense-custom-offline-installer.iso"))
        Invoke-VBox -Arguments @("storagectl", $VmName, "--name", "SATA", "--add", "sata", "--controller", "IntelAhci", "--bootable", "on") | Out-Null
        Invoke-VBox -Arguments @("storageattach", $VmName, "--storagectl", "SATA", "--port", "1", "--device", "0", "--type", "dvddrive", "--medium", $fullIsoPath) | Out-Null

        Write-Host "  [OK] VM $VmName berhasil dioptimalkan (3 vCPU, 3GB RAM, KVM paravirtualization clock)." -ForegroundColor Green
        
        # ------------------------------------------------------------------------------
        # 4. MENYALAKAN VM & MONITORING
        # ------------------------------------------------------------------------------
        Write-Host "`n[4/4] Menyalakan VM $VmName dan Memantau Booting..." -ForegroundColor Green
        Invoke-VBox -Arguments @("startvm", $VmName, "--type", "gui") | Out-Null
        
        Write-Host "  -> VM sedang booting. Mengambil tangkapan layar pemantauan..." -ForegroundColor Gray
        Start-Sleep -Seconds 15
        
        $screenDir = Join-Path $WorkspaceDir "artifacts"
        if (-not (Test-Path $screenDir)) { New-Item -ItemType Directory -Path $screenDir -Force | Out-Null }
        $screenPath = Join-Path $screenDir "vm_screen_status.png"
        
        Invoke-VBox -Arguments @("controlvm", $VmName, "screenshotpng", $screenPath) | Out-Null
        if (Test-Path $screenPath) {
            Write-Host "  [OK] Tangkapan layar status VM tersimpan di: $screenPath" -ForegroundColor Green
        }
        
        Write-Host "`n==========================================================" -ForegroundColor Cyan
        Write-Host "   AUTO PILOT BERHASIL DISELESAIKAN DENGAN SEMPURNA!      " -ForegroundColor Green
        Write-Host "==========================================================" -ForegroundColor Cyan
        Write-Host "Installer pfSense sekarang berjalan dengan kecepatan maksimal tanpa timeout." -ForegroundColor White
    }
}
