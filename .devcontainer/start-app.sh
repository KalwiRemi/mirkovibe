#!/bin/bash
set -e

sudo -n service postgresql start >/dev/null 2>&1 || true

if pgrep -f "php -S 0.0.0.0:8080 -t /workspaces/mirkovibe" >/dev/null 2>&1; then
    exit 0
fi

nohup php -S 0.0.0.0:8080 -t /workspaces/mirkovibe > /tmp/php-server.log 2>&1 < /dev/null &
sleep 1

pgrep -f "php -S 0.0.0.0:8080 -t /workspaces/mirkovibe" >/dev/null 2>&1 || {
    echo "Nie udało się uruchomić serwera PHP."
    tail -n 80 /tmp/php-server.log 2>/dev/null || true
    exit 1
}
