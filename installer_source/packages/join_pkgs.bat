@echo off
setlocal enabledelayedexpansion
title pfSense Offline Package Reassembler
echo ==========================================================
echo     PFSENSE OFFLINE PACKAGE REASSEMBLER (AUTO-JOIN)       
echo ==========================================================

set "ALL_DIR=%~dp0All"
if not exist "%ALL_DIR%" set "ALL_DIR=%~dp0"

for %%F in ("%ALL_DIR%\*.pkg.partaa") do (
    set "PARTAA=%%~fF"
    set "TARGET=!PARTAA:.partaa=!"
    if not exist "!TARGET!" (
        echo [*] Menggabungkan !TARGET!...
        copy /b "!TARGET!.partaa"+"!TARGET!.partab" "!TARGET!" >nul
        echo [OK] Berhasil digabungkan: !TARGET!
    ) else (
        echo [=] Berkas sudah ada: !TARGET!
    )
)
echo.
echo [OK] Seluruh paket repositori offline lengkap dan siap digunakan!
pause
