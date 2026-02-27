# Plan działania – mirkovibe

Alternatywa dla Wykopu i Reddita stworzona przez AI.  
Technologia: PHP, PostgreSQL, htmx. Cały kod aplikacji w jednym pliku (`index.php`).  
Kod pisany po polsku (zmienne, funkcje, tabele, kolumny).

---

## Zasady pracy

- Każde zadanie realizuje jeden agent (lub człowiek).
- Zadanie powinno być małe: jedno zadanie = jeden pull request.
- Wszystkie nazwy zmiennych, funkcji, tabel i kolumn w języku **polskim**.
- Logika aplikacji trzymana w bazie danych (procedury, widoki, funkcje SQL).
- Brak zewnętrznych frameworków PHP – czysty PHP.
- Minimalne UI, bez ozdób – htmx do dynamicznych akcji.

---

## Etap 1 – Fundament bazy danych

### Zadanie 1.1 – `setup.sql`: tabela użytkowników
Stworzyć plik `setup.sql` z tabelą `uzytkownicy`:
- `id` SERIAL PRIMARY KEY
- `nazwa` TEXT UNIQUE NOT NULL
- `haslo_hash` TEXT NOT NULL
- `data_rejestracji` TIMESTAMPTZ DEFAULT NOW()

### Zadanie 1.2 – `setup.sql`: tabela wpisów
Dodać do `setup.sql` tabelę `wpisy` (posty/linki):
- `id` SERIAL PRIMARY KEY
- `tytul` TEXT NOT NULL
- `tresc` TEXT
- `link` TEXT
- `autor_id` INT REFERENCES uzytkownicy(id)
- `data_dodania` TIMESTAMPTZ DEFAULT NOW()
- `wynik` INT DEFAULT 0

### Zadanie 1.3 – `setup.sql`: tabela komentarzy
Dodać do `setup.sql` tabelę `komentarze`:
- `id` SERIAL PRIMARY KEY
- `wpis_id` INT REFERENCES wpisy(id)
- `autor_id` INT REFERENCES uzytkownicy(id)
- `tresc` TEXT NOT NULL
- `data_dodania` TIMESTAMPTZ DEFAULT NOW()
- `wynik` INT DEFAULT 0

### Zadanie 1.4 – `setup.sql`: tabela głosów
Dodać do `setup.sql` tabelę `glosy`:
- `id` SERIAL PRIMARY KEY
- `uzytkownik_id` INT REFERENCES uzytkownicy(id)
- `wpis_id` INT REFERENCES wpisy(id) (nullable)
- `komentarz_id` INT REFERENCES komentarze(id) (nullable)
- `wartosc` SMALLINT NOT NULL CHECK (wartosc IN (1, -1))
- UNIQUE(uzytkownik_id, wpis_id), UNIQUE(uzytkownik_id, komentarz_id)

### Zadanie 1.5 – `setup.sql`: funkcje i widoki SQL
Stworzyć funkcje/widoki pomocnicze w PostgreSQL:
- Widok `wpisy_z_wynikiem` – wpisy z obliczonym wynikiem głosów
- Funkcja `dodaj_glos(uzytkownik_id, wpis_id, wartosc)` – dodaje/aktualizuje głos
- Funkcja `zarejestruj_uzytkownika(nazwa, haslo)` – tworzy użytkownika z zahashowanym hasłem

---

## Etap 2 – Szkielet aplikacji PHP

### Zadanie 2.1 – `index.php`: połączenie z bazą danych
Stworzyć plik `index.php` z:
- Konfiguracją połączenia do PostgreSQL (PDO lub pg_connect)
- Obsługą błędów połączenia
- Zmienne konfiguracyjne (host, baza, użytkownik, hasło) jako stałe PHP

### Zadanie 2.2 – `index.php`: routing
Dodać prosty router w `index.php`:
- Routing oparty na parametrze GET `?strona=`
- Domyślna strona: lista wpisów (`strona=glowna`)
- Obsługiwane ścieżki: `glowna`, `wpis`, `dodaj`, `logowanie`, `rejestracja`, `wyloguj`

### Zadanie 2.3 – `index.php`: layout HTML
Stworzyć bazowy layout HTML w `index.php`:
- Nagłówek z nazwą serwisu i menu nawigacyjnym
- Miejsce na treść strony
- Stopka
- Dołączyć htmx z CDN (`https://unpkg.com/htmx.org`)
- Podstawowy CSS inline (bez zewnętrznych arkuszy)

---

## Etap 3 – Autoryzacja użytkowników

### Zadanie 3.1 – Rejestracja
Zaimplementować w `index.php` stronę `rejestracja`:
- Formularz: nazwa użytkownika, hasło, powtórz hasło
- Walidacja: unikalna nazwa, hasła zgodne, minimalna długość
- Wywołanie funkcji SQL `zarejestruj_uzytkownika()`
- Po sukcesie: przekierowanie do strony logowania

### Zadanie 3.2 – Logowanie
Zaimplementować w `index.php` stronę `logowanie`:
- Formularz: nazwa użytkownika, hasło
- Weryfikacja hasła przez `password_verify()`
- Zapis danych użytkownika w sesji PHP (`$_SESSION`)
- Po sukcesie: przekierowanie na stronę główną

### Zadanie 3.3 – Wylogowanie
Zaimplementować w `index.php` akcję `wyloguj`:
- Zniszczenie sesji PHP
- Przekierowanie na stronę logowania

---

## Etap 4 – Wpisy (posty)

### Zadanie 4.1 – Lista wpisów (strona główna)
Zaimplementować w `index.php` stronę `glowna`:
- Pobranie wpisów z widoku `wpisy_z_wynikiem` (sortowanie: najnowsze)
- Wyświetlenie listy: tytuł, autor, wynik głosów, liczba komentarzy, data
- Paginacja (10 wpisów na stronę)

### Zadanie 4.2 – Dodawanie wpisu
Zaimplementować w `index.php` stronę `dodaj`:
- Dostępna tylko dla zalogowanych użytkowników
- Formularz: tytuł, treść (opcjonalnie), link (opcjonalnie)
- Walidacja: wymagany tytuł, link lub treść musi być podana
- Po dodaniu: przekierowanie do strony wpisu

### Zadanie 4.3 – Strona pojedynczego wpisu
Zaimplementować w `index.php` stronę `wpis`:
- Parametr: `?strona=wpis&id=123`
- Wyświetlenie tytułu, treści/linku, autora, daty, wyniku
- Wyświetlenie listy komentarzy (posortowanych chronologicznie)

---

## Etap 5 – Komentarze

### Zadanie 5.1 – Dodawanie komentarza
Zaimplementować dodawanie komentarza na stronie wpisu:
- Formularz pod listą komentarzy (tylko dla zalogowanych)
- Akcja htmx (`hx-post`) do wysyłania komentarza bez przeładowania strony
- Po dodaniu: odświeżenie listy komentarzy przez htmx (`hx-swap`)

---

## Etap 6 – Głosowanie

### Zadanie 6.1 – Głosowanie na wpisy
Dodać przyciski głosowania (+/-) przy każdym wpisie:
- Przyciski widoczne tylko dla zalogowanych
- Akcja htmx (`hx-post`) do oddania głosu
- Odpowiedź serwera: zaktualizowany wynik (htmx podmienia tylko wartość)
- Wywołanie funkcji SQL `dodaj_glos()`

### Zadanie 6.2 – Głosowanie na komentarze
Dodać przyciski głosowania (+/-) przy każdym komentarzu:
- Analogicznie do głosowania na wpisy

---

## Etap 7 – Migracje i utrzymanie

### Zadanie 7.1 – Plik `migrate.sql`
Stworzyć pusty plik `migrate.sql` z komentarzem instruktażowym:
- Opisać jak dodawać kolejne migracje (z numerem wersji i datą)
- Szablon dla przyszłych migracji

---

## Kolejność realizacji

```
1.1 → 1.2 → 1.3 → 1.4 → 1.5
2.1 → 2.2 → 2.3
3.1 → 3.2 → 3.3
4.1 → 4.2 → 4.3
5.1
6.1 → 6.2
7.1
```

Etapy 1 i 2 muszą być ukończone przed etapami 3–7.  
Etap 3 musi być ukończony przed etapami 4–6.
