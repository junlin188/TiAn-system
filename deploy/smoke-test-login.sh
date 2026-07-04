#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1/}"
COOKIE_FILE="$(mktemp)"
trap 'rm -f "$COOKIE_FILE"' EXIT

curl -fsS -c "$COOKIE_FILE" "$BASE_URL" >/dev/null
curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "${BASE_URL}?action=captcha" >/dev/null

SESSION_ID="$(awk '$6 == "PHPSESSID" { print $7 }' "$COOKIE_FILE" | tail -n 1)"
if [ -z "$SESSION_ID" ]; then
  echo "Could not find PHP session id." >&2
  exit 1
fi

SESSION_FILE="/var/lib/php/sessions/sess_${SESSION_ID}"
CAPTCHA="$(grep -o 'captcha|s:[0-9]*:"[^"]*"' "$SESSION_FILE" | sed -E 's/.*:"([^"]*)"/\1/' | tail -n 1)"
if [ -z "$CAPTCHA" ]; then
  echo "Could not read captcha from PHP session." >&2
  exit 1
fi

curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
  -d "account=admin" \
  -d "password=admin123456" \
  -d "captcha=${CAPTCHA}" \
  "${BASE_URL}?action=login" >/dev/null

HTML="$(curl -fsS -b "$COOKIE_FILE" "${BASE_URL}?page=proposals")"
printf '%s' "$HTML" | grep -q '提案管理'
echo "login-ok"
