-- Migracja uprawnień rejestracji użytkownika
-- Uruchom na istniejącej bazie, jeśli rejestracja zwraca "permission denied for table uzytkownicy"

ALTER FUNCTION zarejestruj_uzytkownika(TEXT, TEXT)
    SECURITY DEFINER
    SET search_path = public, pg_temp;

-- Migracja: funkcja głosowania na komentarze
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
