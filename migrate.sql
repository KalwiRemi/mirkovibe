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

-- Migracja: dwa rodzaje wpisów (wpis i link)
ALTER TABLE wpisy ADD COLUMN IF NOT EXISTS rodzaj TEXT NOT NULL DEFAULT 'wpis';

-- Migracja: panel administratora
ALTER TABLE uzytkownicy ADD COLUMN IF NOT EXISTS jest_adminem BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS konfiguracja (
    klucz   TEXT PRIMARY KEY,
    wartosc TEXT NOT NULL
);

INSERT INTO konfiguracja (klucz, wartosc) VALUES ('rejestracja_wlaczona', 'false')
    ON CONFLICT (klucz) DO NOTHING;

INSERT INTO konfiguracja (klucz, wartosc) VALUES ('minimalny_czas_wpisu', '12')
    ON CONFLICT (klucz) DO NOTHING;

INSERT INTO konfiguracja (klucz, wartosc) VALUES ('minimalny_czas_komentarza', '1')
    ON CONFLICT (klucz) DO NOTHING;

CREATE OR REPLACE VIEW wpisy_z_wynikiem AS
SELECT
    w.id,
    w.tytul,
    w.tresc,
    w.link,
    w.autor_id,
    w.data_dodania,
    COALESCE(SUM(g.wartosc), 0) AS wynik,
    w.rodzaj
FROM wpisy w
LEFT JOIN glosy g ON g.wpis_id = w.id
GROUP BY w.id, w.tytul, w.tresc, w.link, w.autor_id, w.data_dodania, w.rodzaj;
