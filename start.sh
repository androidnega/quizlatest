#!/bin/bash
# Start server: ./start.sh
# Laravel: http://localhost:8000 (LAN: use your machine IP on port 8000)
# Vite (HMR): http://localhost:5173 by default
# Reverb (WebSockets): ws on REVERB_SERVER_PORT (default 8080) — required for live exam updates

cd "$(dirname "$0")" || exit 1

if [ ! -x node_modules/.bin/concurrently ]; then
  echo "start.sh: run npm install first (concurrently is required)." >&2
  exit 1
fi

./node_modules/.bin/concurrently \
  -n "laravel,vite,reverb" \
  -c "blue,magenta,green" \
  "php artisan serve --host=0.0.0.0 --port=8000" \
  "npm run dev" \
  "php artisan reverb:start --host=0.0.0.0"
