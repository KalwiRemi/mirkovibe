#!/bin/bash
set -e

if [ "$(id -u)" -eq 0 ]; then
    service postgresql start
    uruchom_jako_postgres() {
        su - postgres -c "$1"
    }
else
    sudo -n true >/dev/null 2>&1 || {
        echo "Brak bezhaslowego sudo dla użytkownika $(whoami)."
        exit 1
    }
    sudo -n service postgresql start
    uruchom_jako_postgres() {
        sudo -n -u postgres bash -lc "$1"
    }
fi

# Wait for PostgreSQL to be ready
until uruchom_jako_postgres "pg_isready"; do sleep 1; done

# Create user and database (idempotent)
uruchom_jako_postgres "psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='mirkovibe'\"" | grep -q 1 || \
    uruchom_jako_postgres "psql -c \"CREATE USER mirkovibe WITH PASSWORD 'mirkovibe';\""

uruchom_jako_postgres "psql -tc \"SELECT 1 FROM pg_database WHERE datname='mirkovibe'\"" | grep -q 1 || \
    uruchom_jako_postgres "psql -c \"CREATE DATABASE mirkovibe OWNER mirkovibe;\""

# Load schema only if tables don't exist yet
uruchom_jako_postgres "psql -d mirkovibe -tc \"SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='uzytkownicy'\"" \
    | grep -q 1 || uruchom_jako_postgres "psql -d mirkovibe -f /workspaces/mirkovibe/setup.sql"
