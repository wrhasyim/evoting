import subprocess
import time
import re
import os
import sys
import datetime
import urllib.request
import urllib.error

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LOG_FILE = os.path.join(BASE_DIR, "tunnel.log")
URL_FILE = os.path.join(BASE_DIR, "TUNNEL_URL.txt")
STATUS_FILE = os.path.join(BASE_DIR, "tunnel_status.json")

def log(msg):
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{now}] {msg}"
    print(line, flush=True)

def kill_existing_quick_tunnels():
    # Only kill cloudflared processes running quick tunnels (--url http://localhost:80),
    # do NOT kill the Windows service running with --token-file!
    try:
        cmd = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'cloudflared.exe\' -and $_.CommandLine -like \'*--url*\' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"'
        subprocess.run(cmd, shell=True, capture_output=True)
    except Exception as e:
        log(f"Error checking existing processes: {e}")

def extract_url_from_log():
    if not os.path.exists(LOG_FILE):
        return None
    try:
        with open(LOG_FILE, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
            match = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
            if match:
                return match.group(0)
    except Exception:
        pass
    return None

def verify_url(url):
    test_target = f"{url}/evoting/index.php"
    log(f"Verifying connectivity to {test_target}...")
    headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}
    for attempt in range(1, 7):
        try:
            req = urllib.request.Request(test_target, headers=headers)
            with urllib.request.urlopen(req, timeout=10) as response:
                if response.status in (200, 302):
                    log(f"Connectivity verified successfully! (HTTP {response.status})")
                    return True
        except urllib.error.HTTPError as e:
            if e.code in (200, 302, 502, 503):
                log(f"Received HTTP {e.code} on attempt {attempt}/6. Cloudflare is propagating DNS...")
        except Exception as e:
            log(f"Attempt {attempt}/6 waiting for tunnel: {e}")
        time.sleep(3)
    return False

def write_url_files(url):
    with open(URL_FILE, "w", encoding="utf-8") as f:
        f.write(f"=== CLOUDFLARE QUICK TUNNEL AKTIF ===\n")
        f.write(f"Waktu Mulai: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n\n")
        f.write(f"LINK UTAMA:\n{url}\n\n")
        f.write(f"LINK VOTING SISWA & GURU:\n{url}/evoting/\n\n")
        f.write(f"LINK LIVE COUNT PUBLIK:\n{url}/evoting/live_count.php\n\n")
        f.write(f"LINK ADMIN PANEL:\n{url}/evoting/admin/\n")
    
    # Also write a JSON file for programmatic access
    import json
    with open(STATUS_FILE, "w", encoding="utf-8") as f:
        json.dump({
            "status": "online",
            "url": url,
            "voting_url": f"{url}/evoting/",
            "live_count_url": f"{url}/evoting/live_count.php",
            "admin_url": f"{url}/evoting/admin/",
            "updated_at": datetime.datetime.now().isoformat()
        }, f, indent=2)

def run():
    log("Inisialisasi Cloudflare Quick Tunnel Watchdog...")
    kill_existing_quick_tunnels()
    time.sleep(1)

    while True:
        if os.path.exists(LOG_FILE):
            try:
                os.remove(LOG_FILE)
            except Exception:
                pass

        log("Menjalankan cloudflared tunnel ke http://localhost:80...")
        cmd = [
            "cloudflared", "tunnel",
            "--url", "http://localhost:80",
            "--no-autoupdate",
            "--logfile", LOG_FILE
        ]

        proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        log(f"Proses cloudflared berjalan dengan PID: {proc.pid}")

        # Wait for URL to appear in log
        url = None
        for _ in range(30):
            time.sleep(1)
            url = extract_url_from_log()
            if url:
                break
            if proc.poll() is not None:
                log("Proses cloudflared berhenti mendadak!")
                break

        if not url:
            log("Gagal mendapatkan URL Cloudflare. Mencoba ulang dalam 5 detik...")
            if proc.poll() is None:
                proc.terminate()
            time.sleep(5)
            continue

        log(f"==================================================")
        log(f"URL QUICK TUNNEL AKTIF: {url}")
        log(f"Link Voting: {url}/evoting/")
        log(f"Link Live Count: {url}/evoting/live_count.php")
        log(f"Link Admin: {url}/evoting/admin/")
        log(f"==================================================")

        write_url_files(url)
        verify_url(url)

        # Keep alive loop
        log("Tunnel aktif & dipantau terus-menerus agar tidak mati...")
        while proc.poll() is None:
            time.sleep(10)
            # Periodic health check every 10 seconds

        log(f"Peringatan: Proses cloudflared (PID {proc.pid}) terhenti dengan exit code {proc.returncode}!")
        log("Merespawn Cloudflare Tunnel otomatis dalam 3 detik...")
        time.sleep(3)

if __name__ == "__main__":
    run()
