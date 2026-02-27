-- =============================================================================
-- migrate.sql – plik migracji bazy danych
-- =============================================================================
--
-- JAK DODAWAĆ KOLEJNE MIGRACJE:
--
-- 1. Każdą migrację poprzedź blokiem komentarza w formacie:
--
--    -- Migracja v<numer> (<data w formacie RRRR-MM-DD>)
--    -- Opis: <krótki opis zmiany>
--    BEGIN;
--    <polecenia SQL>;
--    COMMIT;
--
-- 2. Numer wersji zwiększaj o 1 przy każdej kolejnej migracji.
-- 3. Migracje stosuj narastająco – nie usuwaj starszych wpisów.
-- 4. Uruchamiaj tylko te migracje, które nie zostały jeszcze zastosowane
--    na docelowej bazie danych.
-- 5. Przykładowy szablon migracji umieszczony jest na końcu tego pliku.
--
-- =============================================================================


-- Migracja v1 (2026-02-10)
-- Opis: Uprawnienia rejestracji użytkownika – SECURITY DEFINER dla funkcji
-- Uruchom na istniejącej bazie, jeśli rejestracja zwraca "permission denied for table uzytkownicy"
BEGIN;

ALTER FUNCTION zarejestruj_uzytkownika(TEXT, TEXT)
    SECURITY DEFINER
    SET search_path = public, pg_temp;

COMMIT;


-- Migracja v2 (2026-02-27)
-- Opis: Dodanie funkcji głosowania na komentarze z SECURITY DEFINER
BEGIN;

CREATE OR REPLACE FUNCTION dodaj_glos_komentarz(p_uzytkownik_id INT, p_komentarz_id INT, p_wartosc SMALLINT)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    INSERT INTO glosy (uzytkownik_id, komentarz_id, wartosc)
    VALUES (p_uzytkownik_id, p_komentarz_id, p_wartosc)
    ON CONFLICT (uzytkownik_id, komentarz_id)
    DO UPDATE SET wartosc = EXCLUDED.wartosc;
END;
$$;

COMMIT;


-- =============================================================================
-- SZABLON DLA PRZYSZŁYCH MIGRACJI (skopiuj poniższy blok i uzupełnij):
-- =============================================================================
--
-- -- Migracja v<numer> (<RRRR-MM-DD>)
-- -- Opis: ...
-- BEGIN;
-- ALTER TABLE ...;
-- COMMIT;
--
-- =============================================================================
