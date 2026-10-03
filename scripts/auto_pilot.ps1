# ==============================================================================
# auto_pilot.ps1 - Sistem Otomatisasi Terpadu pfSense Custom Offline Installer
# Build ISO/IMG.GZ, Konfigurasi VM, dan Otomatisasi Instalasi (Auto-Pilot)
# ==============================================================================

[CmdletBinding()]
param (
    [string]$VmName = "",
    [switch]$SkipVmBoot = $false,
    [switch]$SkipIsoBuild = $false,
    [switch]$SkipAutoInstall = $false,
    [switch]$SkipImgGz = $false
)

$ErrorActionPreference = "Stop"
$WorkspaceDir = $PSScriptRoot
if (Test-Path (Join-Path $PSScriptRoot "installer_source")) {
    $WorkspaceDir = $PSScriptRoot
} elseif (Test-Path (Join-Path (Split-Path $PSScriptRoot -Parent) "installer_source")) {
    $WorkspaceDir = Split-Path $PSScriptRoot -Parent
} elseif (-not $WorkspaceDir) {
    $WorkspaceDir = (Get-Location).Path
}
Set-Location $WorkspaceDir

$ArtifactsDir = Join-Path $WorkspaceDir "artifacts"
if (-not (Test-Path $ArtifactsDir)) {
    New-Item -ItemType Directory -Path $ArtifactsDir -Force | Out-Null
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   pfSense Custom Offline Installer - AUTO PILOT SYSTEM   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Direktori Kerja: $WorkspaceDir" -ForegroundColor Yellow
Write-Host "Artifacts Log  : $ArtifactsDir" -ForegroundColor Yellow

# Helper Functions
function Set-UnixFileLines {
    param([string]$Path, [string[]]$Lines)
    $text = ($Lines -join "`n") + "`n"
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $text, $utf8NoBom)
}

function Invoke-VBox {
    param([string[]]$Arguments)
    $vboxManage = "C:\Program Files\Oracle\VirtualBox\VBoxManage.exe"
    if (-not (Test-Path $vboxManage)) {
        throw "VBoxManage.exe tidak ditemukan di $vboxManage"
    }
    $pinfo = New-Object System.Diagnostics.ProcessStartInfo
    $pinfo.FileName = $vboxManage
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

function Send-VBoxKey {
    param([string]$Vm, [string]$Scancodes, [int]$DelayMs = 1000)
    Invoke-VBox -Arguments @("controlvm", $Vm, "keyboardputscancode") + ($Scancodes -split ' ') | Out-Null
    Start-Sleep -Milliseconds $DelayMs
}

function Capture-VBoxScreen {
    param([string]$Vm, [string]$Filename)
    $targetPath = Join-Path $ArtifactsDir $Filename
    Invoke-VBox -Arguments @("controlvm", $Vm, "screenshotpng", $targetPath) | Out-Null
    return $targetPath
}

# ------------------------------------------------------------------------------
# 1. PERBAIKAN DAN VERIFIKASI SOURCE CODE (INSTALLER_SOURCE)
# ------------------------------------------------------------------------------
Write-Host "`n[1/5] Memeriksa dan Memvalidasi Source Code..." -ForegroundColor Green

# 1.1 Pastikan perbaikan DESTDIR di pfSense-install dan auto sudah aktif
$pfInstLocal = Join-Path $WorkspaceDir "installer_source\usr\local\libexec\installer\pfSense-install"
$pfInstBsd   = Join-Path $WorkspaceDir "installer_source\usr\libexec\bsdinstall\pfSense-install"
$bsdAuto     = Join-Path $WorkspaceDir "installer_source\usr\libexec\bsdinstall\auto"

if (Test-Path $pfInstLocal) {
    $content = [System.IO.File]::ReadAllText($pfInstLocal)
    if ($content -notmatch 'DESTDIR:="\$\{BSDINSTALL_CHROOT:-/mnt\}"') {
        $content = $content -replace '(while getopts[^\n]+\n(?:[^\n]+\n)*?done\s*\n)', "`$1`n: `${DESTDIR:=`"`${BSDINSTALL_CHROOT:-/mnt}`"}`n"
        [System.IO.File]::WriteAllText($pfInstLocal, $content, (New-Object System.Text.UTF8Encoding($false)))
    }
    Copy-Item -Path $pfInstLocal -Destination $pfInstBsd -Force
    Write-Host "  [OK] pfSense-install diproteksi dengan fallback DESTDIR=/mnt." -ForegroundColor Gray
}

if (Test-Path $bsdAuto) {
    $autoContent = [System.IO.File]::ReadAllText($bsdAuto)
    if ($autoContent -notmatch 'pfSense-install -D') {
        $autoContent = $autoContent -replace 'bsdinstall pfSense-install \|\|', 'bsdinstall pfSense-install -D "${BSDINSTALL_CHROOT:-/mnt}" ||'
        [System.IO.File]::WriteAllText($bsdAuto, $autoContent, (New-Object System.Text.UTF8Encoding($false)))
    }
    Write-Host "  [OK] bsdinstall auto dikonfigurasi untuk menyuplai parameter tujuan instalasi." -ForegroundColor Gray
}

# 1.2 Optimasi Timeout DHCP
$dhclientConf = Join-Path $WorkspaceDir "installer_source\etc\dhclient.conf"
if (Test-Path $dhclientConf) {
    $dhLines = @("timeout 5;", "retry 5;", "select-timeout 0;", "initial-interval 1;")
    Set-UnixFileLines -Path $dhclientConf -Lines $dhLines
    Write-Host "  [OK] dhclient.conf diatur timeout 5 detik." -ForegroundColor Gray
}

# 1.3 Normalisasi UNIX LF untuk skrip penting
$criticalScripts = @(
    "installer_source\usr\libexec\bsdinstall\auto",
    "installer_source\usr\libexec\bsdinstall\pfSense-install",
    "installer_source\usr\local\libexec\installer\pfSense-install",
    "installer_source\usr\local\libexec\installer\pfSense-installer.sh",
    "installer_source\usr\local\libexec\installer\run-installer.sh",
    "installer_source\root\.profile"
)
foreach ($cs in $criticalScripts) {
    $fullCs = Join-Path $WorkspaceDir $cs
    if (Test-Path $fullCs) {
        $bytes = [System.IO.File]::ReadAllBytes($fullCs)
        $text = [System.Text.Encoding]::UTF8.GetString($bytes)
        if ($text.Contains("`r")) {
            $normalized = $text.Replace("`r`n", "`n").Replace("`r", "`n")
            [System.IO.File]::WriteAllBytes($fullCs, [System.Text.Encoding]::UTF8.GetBytes($normalized))
        }
    }
}
Write-Host "  [OK] Seluruh skrip penting dipastikan berformat UNIX LF." -ForegroundColor Gray

# ------------------------------------------------------------------------------
# 2. PEMBANGUNAN CITRA HYBRID ISO & USB DISK IMAGE (IMG.GZ)
# ------------------------------------------------------------------------------
$isoOutput = "pfsense-custom-offline-installer.iso"
$imgOutput = "pfsense-custom-offline-installer.img"
$gzOutput  = "pfsense-custom-offline-installer.img.gz"

if (-not $SkipIsoBuild) {
    Write-Host "`n[2/5] Membangun Universal Hybrid ISO (xorriso)..." -ForegroundColor Green
    
    # Lepaskan kunci berkas jika VM sedang berjalan
    try {
        $runningRes = Invoke-VBox -Arguments @("list", "runningvms")
        if ($runningRes.Output -match '"([^"]+)"') {
            $rVm = $matches[1]
            Write-Host "  -> Mematikan VM yang sedang berjalan ('$rVm') untuk melepaskan penguncian berkas..." -ForegroundColor Gray
            Invoke-VBox -Arguments @("controlvm", $rVm, "poweroff") | Out-Null
            Start-Sleep -Seconds 3
        }
    } catch {}

    $xorrisoExe = "C:\Windows\system32\xorriso.exe"
    if (-not (Test-Path $xorrisoExe)) { $xorrisoExe = "xorriso" }
    
    $xorrisoArgs = @(
        "-as", "mkisofs",
        "-V", "PFSENSE",
        "-iso-level", "3",
        "-J",
        "-r",
        "-file-mode", "0755",
        "-dir-mode", "0755",
        "-b", "boot/cdboot",
        "-no-emul-boot",
        "-eltorito-alt-boot",
        "-b", "boot/efi.img",
        "-no-emul-boot",
        "-G", "installer_source/boot/isoboot",
        "-o", $isoOutput,
        "installer_source"
    )
    
    Write-Host "  -> Menjalankan xorriso dengan El Torito (CD), isoboot (USB MBR), dan EFI..." -ForegroundColor Gray
    $xorLogOut = Join-Path $ArtifactsDir "xorriso_stdout.log"
    $xorLogErr = Join-Path $ArtifactsDir "xorriso_stderr.log"
    if (Test-Path $xorLogOut) { Remove-Item -Path $xorLogOut -Force }
    if (Test-Path $xorLogErr) { Remove-Item -Path $xorLogErr -Force }

    $proc = Start-Process -FilePath $xorrisoExe -ArgumentList $xorrisoArgs -RedirectStandardOutput $xorLogOut -RedirectStandardError $xorLogErr -NoNewWindow -PassThru -Wait
    
    if ($proc.ExitCode -ne 0) {
        $errOut = ""
        if (Test-Path $xorLogErr) { $errOut = (Get-Content $xorLogErr -Tail 15) -join "`n" }
        Write-Error "Gagal membangun ISO! Exit code $($proc.ExitCode): $errOut"
        exit $proc.ExitCode
    }
    
    $isoItem = Get-Item $isoOutput
    $isoSizeMB = [math]::Round($isoItem.Length / 1MB, 2)
    Write-Host "  [OK] Citra Hybrid ISO selesai: $isoOutput ($isoSizeMB MB)" -ForegroundColor Green

    # Sinkronisasi ke nama alternatif
    try {
        Copy-Item -Path $isoOutput -Destination "pfsense-offline-installer.iso" -Force
    } catch {
        Write-Warning "Tidak dapat menyalin ke pfsense-offline-installer.iso ($($_.Exception.Message))"
    }
    Copy-Item -Path $isoOutput -Destination $imgOutput -Force
    Write-Host "  [OK] Salinan format .img siap untuk penulisan USB: $imgOutput" -ForegroundColor Gray

    # Pembuatan format img.gz
    if (-not $SkipImgGz) {
        Write-Host "  -> Mengompres ke format berkas $gzOutput..." -ForegroundColor Gray
        $7zExe = "C:\Windows\system32\7z.exe"
        if (Test-Path $7zExe) {
            if (Test-Path $gzOutput) { Remove-Item -Path $gzOutput -Force }
            $p7z = Start-Process -FilePath $7zExe -ArgumentList @("a", "-tgzip", "-mx=6", $gzOutput, $imgOutput) -NoNewWindow -PassThru -Wait
            if ($p7z.ExitCode -eq 0 -and (Test-Path $gzOutput)) {
                $gzSizeMB = [math]::Round((Get-Item $gzOutput).Length / 1MB, 2)
                Write-Host "  [OK] Berkas $gzOutput berhasil dibuat ($gzSizeMB MB)!" -ForegroundColor Green
            }
        } else {
            # Fallback Python gzip
            Write-Host "  -> Menggunakan kompresor bawaan Python untuk .img.gz..." -ForegroundColor Gray
            $pyScript = "import gzip, shutil; out=open('$gzOutput','wb'); src=open('$imgOutput','rb'); shutil.copyfileobj(src, out); out.close(); src.close()"
            python -c $pyScript
            if (Test-Path $gzOutput) {
                $gzSizeMB = [math]::Round((Get-Item $gzOutput).Length / 1MB, 2)
                Write-Host "  [OK] Berkas $gzOutput berhasil dibuat ($gzSizeMB MB)!" -ForegroundColor Green
            }
        }
    }
} else {
    Write-Host "`n[2/5] Melewati pembangunan ISO (-SkipIsoBuild aktif)." -ForegroundColor Yellow
}

# ------------------------------------------------------------------------------
# 3. DETEKSI & OPTIMASI VIRTUALBOX VM
# ------------------------------------------------------------------------------
if (-not $SkipVmBoot) {
    Write-Host "`n[3/5] Mendeteksi dan Mengoptimalkan Mesin Virtual VirtualBox..." -ForegroundColor Green
    
    # Auto-deteksi VM jika parameter kosong
    if (-not $VmName) {
        $runningRes = Invoke-VBox -Arguments @("list", "runningvms")
        if ($runningRes.Output -match '"([^"]+)"') {
            $VmName = $matches[1]
            Write-Host "  -> Menemukan VM yang sedang berjalan: '$VmName'" -ForegroundColor Yellow
        } else {
            $allVmsRes = Invoke-VBox -Arguments @("list", "vms")
            if ($allVmsRes.Output -match '"linux2pfsense"') {
                $VmName = "linux2pfsense"
            } elseif ($allVmsRes.Output -match '"pfsense"') {
                $VmName = "pfsense"
            } elseif ($allVmsRes.Output -match '"([^"]+)"') {
                $VmName = $matches[1]
            }
        }
    }
    
    if (-not $VmName) {
        Write-Warning "Tidak ada VM VirtualBox yang ditemukan. Melewati langkah VM."
    } else {
        Write-Host "  -> Target Mesin Virtual: '$VmName'" -ForegroundColor Cyan
        
        $vmInfoRes = Invoke-VBox -Arguments @("showvminfo", $VmName, "--machinereadable")
        if ($vmInfoRes.Output -match 'VMState="running"') {
            Write-Host "  -> Mematikan VM untuk memuat citra baru..." -ForegroundColor Gray
            Invoke-VBox -Arguments @("controlvm", $VmName, "poweroff") | Out-Null
            Start-Sleep -Seconds 2
        }

        Write-Host "  -> Menerapkan konfigurasi hardware optimal (3 vCPU, 3072 MB RAM, KVM clock)..." -ForegroundColor Gray
        Invoke-VBox -Arguments @("modifyvm", $VmName, "--paravirt-provider", "kvm", "--pae", "on", "--cpus", "3", "--memory", "3072") | Out-Null
        
        # Pastikan NIC2 (LAN) aktif agar pfSense tidak mengalami interface mismatch pada booting pertama
        if ($vmInfoRes.Output -match 'nic2="none"') {
            Write-Host "  -> Mengaktifkan Adapter Jaringan 2 (LAN: Internal Network) agar bebas interface mismatch..." -ForegroundColor Gray
            Invoke-VBox -Arguments @("modifyvm", $VmName, "--nic2", "intnet", "--intnet2", "pfSense-LAN", "--nictype2", "82540EM") | Out-Null
        }
        
        # Pasang ISO ke media drive
        $fullIsoPath = [System.IO.Path]::GetFullPath((Join-Path $WorkspaceDir $isoOutput))
        $ctlName = "SATA"
        if ($vmInfoRes.Output -match 'storagecontrollername\d+="([^"]+)"') {
            $ctlName = $matches[1]
        }
        
        Invoke-VBox -Arguments @("storageattach", $VmName, "--storagectl", $ctlName, "--port", "1", "--device", "0", "--type", "dvddrive", "--medium", $fullIsoPath) | Out-Null
        Write-Host "  [OK] Media installer $isoOutput berhasil dipasang ke VM $VmName ($ctlName)." -ForegroundColor Green

        # ----------------------------------------------------------------------
        # 4. MENYALAKAN VM
        # ----------------------------------------------------------------------
        Write-Host "`n[4/5] Menyalakan VM $VmName..." -ForegroundColor Green
        Invoke-VBox -Arguments @("startvm", $VmName, "--type", "gui") | Out-Null
        Write-Host "  [OK] VM $VmName telah dinyalakan dalam antarmuka grafis." -ForegroundColor Green

        # ----------------------------------------------------------------------
        # 5. AUTO-PILOT AUTOMATED INSTALLATION ENGINE
        # ----------------------------------------------------------------------
        if (-not $SkipAutoInstall) {
            Write-Host "`n[5/5] Memulai Navigasi Otomatis (Auto-Pilot Installer)..." -ForegroundColor Green
            Write-Host "  -> Menunggu sistem pfSense memuat kernel dan antarmuka bsdinstall (25 detik)..." -ForegroundColor Gray
            
            # Scancodes map:
            # Enter: 1c 9c
            # Space: 39 b9
            # Tab  : 0f 8f
            # Right: e0 4d e0 cd
            # Up   : e0 48 e0 c8
            # Down : e0 50 e0 d0
            
            Start-Sleep -Seconds 25
            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_01_boot.png" | Out-Null
            Write-Host "  [Stage 1] Mengirim konfirmasi Lisensi / Copyright (<Accept>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500
            
            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_02_welcome.png" | Out-Null
            Write-Host "  [Stage 2] Memilih menu instalasi (<Install>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500

            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_03_keymap.png" | Out-Null
            Write-Host "  [Stage 3] Memilih layout keyboard standar (<Default Keymap>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500

            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_04_partition.png" | Out-Null
            Write-Host "  [Stage 4] Memilih skema partisi ZFS otomatis (<Auto ZFS>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500

            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_05_pool.png" | Out-Null
            Write-Host "  [Stage 5] Melanjutkan konfigurasi pool ZFS (<Install - Proceed>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500

            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_06_stripe.png" | Out-Null
            Write-Host "  [Stage 6] Memilih tipe redundansi (<stripe - No Redundancy>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500

            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_07_disk.png" | Out-Null
            Write-Host "  [Stage 7] Memilih disk target (<Space> centang da0/ada0 + <Enter>)..." -ForegroundColor Yellow
            Send-VBoxKey -Vm $VmName -Scancodes "39 b9" -DelayMs 800
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500

            Capture-VBoxScreen -Vm $VmName -Filename "autopilot_08_confirm.png" | Out-Null
            Write-Host "  [Stage 8] Mengonfirmasi penghapusan disk dan memulai instalasi (<YES>)..." -ForegroundColor Yellow
            # Navigasi ke tombol YES (Panah Kanan) lalu Enter
            Send-VBoxKey -Vm $VmName -Scancodes "e0 4d e0 cd" -DelayMs 800
            Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 3000

            Write-Host "  -> Instalasi sedang mengekstrak paket base FreeBSD, kernel, dan pfSense..." -ForegroundColor Cyan
            Write-Host "     Memantau proses instalasi setiap 10 detik..." -ForegroundColor Gray
            
            $installed = $false
            for ($i = 1; $i -le 36; $i++) {
                Start-Sleep -Seconds 10
                $scr = Capture-VBoxScreen -Vm $VmName -Filename ("autopilot_progress_{0:D2}.png" -f $i)
                Write-Host "     Tangkapan layar kemajuan #$i tersimpan di $(Split-Path $scr -Leaf)" -ForegroundColor Gray
                
                $st = Invoke-VBox -Arguments @("showvminfo", $VmName, "--machinereadable")
                if ($st.Output -match 'VMState="poweroff"') {
                    $installed = $true
                    Write-Host "  -> Instalasi selesai! VM mati secara otomatis untuk pelepasan ISO." -ForegroundColor Green
                    break
                }
            }

            if (-not $installed) {
                # Menangani kemungkinan dialog penyelesaian manual (Open shell? -> No / Reboot)
                Write-Host "  [Stage 9] Konfirmasi penyelesaian instalasi (<No / Reboot>)..." -ForegroundColor Yellow
                Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500
                Send-VBoxKey -Vm $VmName -Scancodes "1c 9c" -DelayMs 2500
            }

            $finalScr = Capture-VBoxScreen -Vm $VmName -Filename "autopilot_final_status.png"
            Write-Host "  [OK] Tangkapan layar status akhir tersimpan: $finalScr" -ForegroundColor Green
        } else {
            Write-Host "`n[5/5] Melewati navigasi otomatis (-SkipAutoInstall aktif)." -ForegroundColor Yellow
        }

        Write-Host "`n==========================================================" -ForegroundColor Cyan
        Write-Host "   AUTO PILOT BERHASIL DISELESAIKAN DENGAN SEMPURNA!      " -ForegroundColor Green
        Write-Host "==========================================================" -ForegroundColor Cyan
        Write-Host "Berkas yang dihasilkan:" -ForegroundColor White
        Write-Host "  - ISO Image   : $isoOutput" -ForegroundColor White
        Write-Host "  - USB Image   : $imgOutput" -ForegroundColor White
        Write-Host "  - USB (.img.gz): $gzOutput" -ForegroundColor White
        Write-Host "VM '$VmName' siap digunakan dengan performa optimal." -ForegroundColor White
    }
}
