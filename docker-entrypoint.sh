#!/bin/bash
set -euo pipefail

# PHP/Apache service only - Python eye tracking runs in separate Railway service
echo "[entrypoint] Starting Apache/PHP service"

exec apache2-foreground
