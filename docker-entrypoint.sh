#!/bin/bash
set -euo pipefail

EYE_TRACKING_HOST="${EYE_TRACKING_HOST:-0.0.0.0}"
EYE_TRACKING_PORT="${EYE_TRACKING_PORT:-5000}"

export EYE_TRACKING_HOST
export EYE_TRACKING_PORT

echo "[entrypoint] Starting eye tracking DATA API on ${EYE_TRACKING_HOST}:${EYE_TRACKING_PORT}"
python3 /var/www/html/python_services/eye_tracking_api.py &
PY_PID=$!

cleanup() {
  echo "[entrypoint] Stopping eye tracking API..."
  kill "${PY_PID}" 2>/dev/null || true
}

trap cleanup SIGTERM SIGINT

exec apache2-foreground

