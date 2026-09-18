#!/bin/sh
# Entrypoint: tunggu MySQL siap, auto-install DB (idempotent), lalu jalan Apache.
set -e

# NOTE: printenv gagal (exit 1) bila var tidak diset — wajib '|| true'
# karena 'set -e' akan membunuh script (= restart loop) jika tidak.
DB_HOST="$(printenv 'database.default.hostname' || true)"
DB_PORT="$(printenv 'database.default.port' || true)"
DB_USER="$(printenv 'database.default.username' || true)"
DB_PASS="$(printenv 'database.default.password' || true)"
: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_USER:=ai_agent}"

export DBH="$DB_HOST" DBU="$DB_USER" DBP="$DB_PASS" DBPt="$DB_PORT"

# Saklar darurat: set SKIP_INSTALL=1 (atau app.skipInstall=true) untuk
# melewati tunggu-DB + auto-install. Aplikasi tetap jalan; setup DB
# bisa lewat halaman web /install setelah DB siap.
SKIP="$(printenv 'SKIP_INSTALL' || true)"
SKIP2="$(printenv 'app.skipInstall' || true)"
if [ "$SKIP" = "1" ] || [ "$SKIP" = "true" ] || [ "$SKIP2" = "1" ] || [ "$SKIP2" = "true" ]; then
  echo "[entrypoint] SKIP_INSTALL aktif — lewati auto-install."
  echo "[entrypoint] Start: $*"
  exec "$@"
fi

echo "[entrypoint] Menunggu MySQL di ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r '$c=@new mysqli(getenv("DBH"),getenv("DBU"),getenv("DBP"),"", (int)getenv("DBPt")); exit($c->connect_errno?1:0);' 2>/dev/null; do
  i=$((i+1))
  if [ "$i" -ge 60 ]; then
    echo "[entrypoint] MySQL tidak siap setelah ~120 dtk, lanjut apa adanya."
    break
  fi
  sleep 2
done

echo "[entrypoint] Menjalankan installer (app:install --auto) ..."
php spark app:install --auto || echo "[entrypoint] installer gagal, cek log di atas."
chown -R www-data:www-data writable 2>/dev/null || true

echo "[entrypoint] Start: $*"
exec "$@"
