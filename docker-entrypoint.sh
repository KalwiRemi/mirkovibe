#!/bin/sh
set -e

if [ -n "$SMTP_HOST" ]; then
    cat > /etc/msmtprc << EOF
defaults
auth on
tls on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
logfile -

account default
host $SMTP_HOST
port ${SMTP_PORT:-587}
from ${SMTP_FROM:-noreply@mirkovibe.fly.dev}
user ${SMTP_USER:-}
password ${SMTP_PASS:-}
EOF
    chown www-data:www-data /etc/msmtprc
    chmod 600 /etc/msmtprc
    echo 'sendmail_path = "/usr/bin/msmtp --file=/etc/msmtprc -t --read-envelope-from"' > /usr/local/etc/php/conf.d/mail.ini
else
    echo "UWAGA: Zmienna SMTP_HOST nie jest ustawiona. Wysyłanie emaili jest wyłączone." >&2
fi

exec "$@"
