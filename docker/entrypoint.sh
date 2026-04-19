#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${CREATE_STORAGE_LINK:-false}" = "true" ]; then
    php artisan storage:link || true
fi

mkdir -p /var/run/supervisor /etc/supervisor/conf.d

write_supervisor_config() {
    cat >/etc/supervisor/conf.d/supervisord.conf <<EOF
[supervisord]
nodaemon=true
logfile=/dev/null
pidfile=/tmp/supervisord.pid

[program:web]
command=php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
directory=/var/www/html
autostart=true
autorestart=true
priority=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue]
process_name=%(program_name)s_%(process_num)02d
numprocs=${QUEUE_WORKER_PROCESSES:-4}
command=php artisan queue:work --queue=${QUEUE_NAMES:-default} --tries=${QUEUE_TRIES:-3} --sleep=${QUEUE_SLEEP:-3} --timeout=${QUEUE_TIMEOUT:-0}
directory=/var/www/html
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
priority=20
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:scheduler]
command=sh -c 'while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done'
directory=/var/www/html
autostart=true
autorestart=true
priority=25
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:extraction]
command=python -m uvicorn app.main:app --host ${EXTRACTION_BIND_HOST:-127.0.0.1} --port ${EXTRACTION_BIND_PORT:-8001} --workers ${EXTRACTION_SERVICE_WORKERS:-4}
directory=/var/www/html/services/extraction-service
autostart=true
autorestart=true
priority=30
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF
}

case "${CONTAINER_ROLE:-all}" in
    all)
        write_supervisor_config
        exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
        ;;
    web)
        exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
        ;;
    worker)
        exec php artisan queue:work --queue="${QUEUE_NAMES:-default}" --tries="${QUEUE_TRIES:-3}" --sleep="${QUEUE_SLEEP:-3}" --timeout="${QUEUE_TIMEOUT:-0}"
        ;;
    extraction)
        cd /var/www/html/services/extraction-service
        exec python -m uvicorn app.main:app --host="${EXTRACTION_BIND_HOST:-127.0.0.1}" --port="${EXTRACTION_BIND_PORT:-8001}" --workers="${EXTRACTION_SERVICE_WORKERS:-4}"
        ;;
    scheduler)
        exec sh -c 'while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done'
        ;;
    custom)
        exec "$@"
        ;;
esac

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

write_supervisor_config
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf