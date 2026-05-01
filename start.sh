#!/bin/bash
# Start server: ./start.sh
# Access locally: http://localhost:8000
# Access from network: http://192.168.1.38:8000

cd "$(dirname "$0")" || exit 1

php artisan serve --host=0.0.0.0 --port=8000
