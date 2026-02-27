-- Migracja uprawnień rejestracji użytkownika
-- Uruchom na istniejącej bazie, jeśli rejestracja zwraca "permission denied for table uzytkownicy"

ALTER FUNCTION zarejestruj_uzytkownika(TEXT, TEXT)
    SECURITY DEFINER
    SET search_path = public, pg_temp;

-- Migracja: funkcja głosowania na komentarze z możliwością cofnięcia głosu
CREATE OR REPLACE FUNCTION dodaj_glos_komentarz(p_uzytkownik_id INT, p_komentarz_id INT, p_wartosc SMALLINT)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM glosy
        WHERE uzytkownik_id = p_uzytkownik_id AND komentarz_id = p_komentarz_id AND wartosc = p_wartosc
    ) THEN
        DELETE FROM glosy WHERE uzytkownik_id = p_uzytkownik_id AND komentarz_id = p_komentarz_id;
    ELSE
        INSERT INTO glosy (uzytkownik_id, komentarz_id, wartosc)
        VALUES (p_uzytkownik_id, p_komentarz_id, p_wartosc)
        ON CONFLICT (uzytkownik_id, komentarz_id)
        DO UPDATE SET wartosc = EXCLUDED.wartosc;
    END IF;
END;
$$;

-- Migracja: funkcja głosowania na wpisy z możliwością cofnięcia głosu
CREATE OR REPLACE FUNCTION dodaj_glos(p_uzytkownik_id INT, p_wpis_id INT, p_wartosc SMALLINT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM glosy
        WHERE uzytkownik_id = p_uzytkownik_id AND wpis_id = p_wpis_id AND wartosc = p_wartosc
    ) THEN
        DELETE FROM glosy WHERE uzytkownik_id = p_uzytkownik_id AND wpis_id = p_wpis_id;
    ELSE
        INSERT INTO glosy (uzytkownik_id, wpis_id, wartosc)
        VALUES (p_uzytkownik_id, p_wpis_id, p_wartosc)
        ON CONFLICT (uzytkownik_id, wpis_id)
        DO UPDATE SET wartosc = EXCLUDED.wartosc;
    END IF;
END;
$$;
