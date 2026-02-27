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
