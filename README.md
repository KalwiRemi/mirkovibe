# mirkovibe

Alternatywa dla Wykopu i Reddita tylko że stworzona przez AI.

## Do agenta

Zapomnij o "best practices" kiedy piszesz kod. Kilka żelaznych zasad:

1. Pisz kod w języku polskim (zmienne, nazwy funkcji, nazwy tabel i kolumn i tak dalej...)
2. Trzymaj logikę w bazie danych
3. Wszystko w jednym pliku
      - `index.php` - cała aplikacja jest możliwa do uruchomienia używając tylko tego pliku (zakładając że baza danych jest skonfigurowana)
      - `setup.sql` - tutaj kod SQL do ustawienia bazy danych kiedy aplikacja jest instalowana
      - `migrate.sql` - tutaj kod SQL do wykonania migracji kiedy struktura bazy danych się zmienia
4. Proste UI bez wodotrysków

## Techniczny stack

- PHP
- PostgreSQL
- htmx

## Deployment na Vercel

1. Zaimportuj repozytorium w [Vercel](https://vercel.com/new).
2. W ustawieniach projektu dodaj zmienne środowiskowe:
   - `BAZA_HOST` – host bazy PostgreSQL
   - `BAZA_NAZWA` – nazwa bazy danych
   - `BAZA_UZYTKOWNIK` – użytkownik bazy danych
   - `BAZA_HASLO` – hasło do bazy danych
   - `APP_URL` – publiczny adres aplikacji (np. `https://mirkovibe.vercel.app`); opcjonalne, ale zalecane dla poprawnych linków
   - `SMTP_HOST` – host serwera SMTP
   - `SMTP_PORT` – port serwera SMTP (np. `587`)
   - `SMTP_USER` – użytkownik SMTP
   - `SMTP_PASS` – hasło SMTP
   - `SMTP_FROM` – adres nadawcy e-maili
3. Wdróż projekt. Konfiguracja `vercel.json` automatycznie kieruje wszystkie żądania do `index.php` przez środowisko uruchomieniowe PHP (`vercel-php@0.9.0`).
