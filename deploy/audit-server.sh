#!/usr/bin/env bash
set -euo pipefail

echo "== OS =="
cat /etc/os-release || true

echo
echo "== Listening ports =="
ss -tulpn || true

echo
echo "== Nginx enabled sites =="
ls -la /etc/nginx/sites-enabled 2>/dev/null || true

echo
echo "== Nginx config test =="
nginx -t 2>&1 || true

echo
echo "== Web directories =="
find /var/www -maxdepth 3 -type d -print 2>/dev/null || true

echo
echo "== Docker containers =="
docker ps -a 2>/dev/null || true

echo
echo "== Common app services =="
systemctl list-units --type=service --state=running --no-pager | grep -Ei 'nginx|apache|mysql|mariadb|postgres|redis|docker|java|node|php|tomcat|supervisor' || true

echo
echo "== Disk usage =="
df -h

echo
echo "== Large top-level app directories =="
du -h --max-depth=1 /var/www /opt /srv 2>/dev/null || true
