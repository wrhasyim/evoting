@echo off
title Cloudflare Tunnel - E-Voting SMK Taruna Karya Mandiri
color 0A

echo.
echo  ============================================================
echo    CLOUDFLARE QUICK TUNNEL - E-Voting SMK TKM
echo  ============================================================
echo.
echo  [INFO] Memulai Cloudflare Tunnel ke http://localhost:80...
echo  [INFO] Tekan CTRL+C untuk menghentikan tunnel.
echo.

cloudflared tunnel --url http://localhost:80 --no-autoupdate 2>&1

pause
