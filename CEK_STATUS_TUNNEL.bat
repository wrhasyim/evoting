@echo off
title Status Cloudflare Quick Tunnel - E-Voting SMK TKM
color 0B

echo.
echo ============================================================
echo       STATUS CLOUDFLARE QUICK TUNNEL (E-VOTING SMK TKM)
echo ============================================================
echo.

if not exist TUNNEL_URL.txt (
    echo [STATUS] File TUNNEL_URL.txt belum ditemukan.
    echo Tunnel kemungkinan belum dimulai.
    goto END
)

type TUNNEL_URL.txt

echo.
echo ============================================================
echo [TIPS]
echo 1. Jangan matikan komputer / koneksi internet agar tunnel tetap aktif.
echo 2. Daemon pemantau sedang berjalan otomatis di latar belakang.
echo ============================================================
echo.

:END
pause
