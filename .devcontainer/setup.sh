#!/bin/bash
set -e

# Wait for PostgreSQL to be ready
until sudo -u postgres pg_isready; do sleep 1; done

# Create user and database (idempotent)
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='mirkovibe'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER mirkovibe WITH PASSWORD 'mirkovibe';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='mirkovibe'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE mirkovibe OWNER mirkovibe;"

# Load schema only if tables don't exist yet
sudo -u postgres psql -d mirkovibe -tc \
    "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='uzytkownicy'" \
    | grep -q 1 || sudo -u postgres psql -d mirkovibe -f /workspaces/mirkovibe/setup.sql
