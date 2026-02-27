# Mirkovibe – Copilot Instructions

Mirkovibe to prosta alternatywa dla Wykopu i Reddita, zbudowana w PHP, PostgreSQL i htmx.

## Zasady nadrzędne (nie łam ich nigdy)

1. **Pisz kod po polsku** – nazwy zmiennych, funkcji, tabel, kolumn, kluczy sesji i tras URL muszą być po polsku.
2. **Trzymaj logikę w bazie danych** – złożone operacje realizuj przez funkcje i widoki PostgreSQL, a nie przez kod PHP.
3. **Wszystko w jednym pliku** – cała logika aplikacji żyje w `index.php`. Nie twórz dodatkowych plików PHP.
4. **Proste UI** – używaj czystego HTML i htmx. Nie dodawaj frameworków JS ani zewnętrznych bibliotek CSS poza tym, co już jest w projekcie.

## Struktura plików

- `index.php` – cała aplikacja (routing, logika, widoki)
- `setup.sql` – schemat bazy danych (tabele, widoki, funkcje); uruchamiany raz przy instalacji
- `migrate.sql` – migracje (zmiany schematu); uruchamiany przy wdrożeniu gdy plik się zmienił
- `docs/ficzery.md` – opis wszystkich ficzerów aplikacji (aktualizuj po każdej zmianie ficzerów)
- `.devcontainer/` – konfiguracja środowiska deweloperskiego
- `.github/workflows/` – CI/CD (wdrożenie na produkcję przez `deploy.yml`)

## Stos technologiczny

- **PHP** – logika aplikacji
- **PostgreSQL** – baza danych (z rozszerzeniem `pgcrypto` do haszowania haseł)
- **htmx** – dynamiczne akcje bez przeładowania strony
- **Apache mod_rewrite** – przyjazne adresy URL (`.htaccess`)

## Kluczowe elementy schematu bazy

- `uzytkownicy` – użytkownicy; kolumna `jest_adminem` oznacza administratora
- `konfiguracja` – ustawienia aplikacji (klucz–wartość); np. `rejestracja_wlaczona` (`true`/`false`, domyślnie `false`)
- `wpisy` – wpisy tekstowe i linki (`rodzaj`: `wpis` lub `link`)
- `komentarze` – komentarze do wpisów
- `glosy` – głosy na wpisy i komentarze (wartość `1` lub `-1`)
- widok `wpisy_z_wynikiem` – wpisy z wyliczonym wynikiem głosowania

## Środowisko deweloperskie

Projekt używa Dev Container. Po otwarciu w VS Code lub Codespaces:

1. Baza PostgreSQL startuje automatycznie na `localhost:5432`.
2. Serwer PHP startuje automatycznie na porcie `8080`.
3. Schemat bazy jest ładowany z `setup.sql` (tylko gdy tabele jeszcze nie istnieją).

Zmienne środowiskowe połączenia z bazą:
- `BAZA_HOST` – host bazy danych
- `BAZA_NAZWA` – nazwa bazy
- `BAZA_UZYTKOWNIK` – użytkownik
- `BAZA_HASLO` – hasło
- `NAZWA_ADMINISTRATORA` – opcjonalna; nazwa użytkownika, który automatycznie otrzymuje rolę administratora po rejestracji

## Jak wprowadzać zmiany

### Zmiany w aplikacji (index.php)
- Edytuj `index.php`. Serwer PHP automatycznie odzwierciedla zmiany.

### Zmiany schematu bazy danych
- **Nowa instalacja** – dodaj do `setup.sql`.
- **Migracja istniejącej bazy** – dodaj do `migrate.sql`. Pipeline CI/CD (`deploy.yml`) wykrywa zmiany w `migrate.sql` i automatycznie uruchamia migracje.

### Zmiany ficzerów
- Zaktualizuj `docs/ficzery.md`, aby odzwierciedlał aktualny stan aplikacji.

## Styl kodu

- Stosuj wcięcia spacjami (jak w istniejącym kodzie).
- Nie dodawaj komentarzy po angielsku – jeśli komentarz jest potrzebny, pisz go po polsku.
- Trzymaj się wzorców już użytych w `index.php` (routing przez `$_GET['strona']`, sesje PHP, zapytania przez PDO).
