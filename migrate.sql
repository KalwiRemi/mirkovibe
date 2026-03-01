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

INSERT INTO konfiguracja (klucz, wartosc) VALUES ('limit_wpisow_godzina', '5')
    ON CONFLICT (klucz) DO NOTHING;

INSERT INTO konfiguracja (klucz, wartosc) VALUES ('limit_komentarzy_godzina', '20')
    ON CONFLICT (klucz) DO NOTHING;

DROP VIEW IF EXISTS wpisy_z_wynikiem;

-- Migracja: weryfikacja email podczas rejestracji
ALTER TABLE uzytkownicy ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE uzytkownicy ADD COLUMN IF NOT EXISTS email_zweryfikowany BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE uzytkownicy ALTER COLUMN email_zweryfikowany SET DEFAULT FALSE;
CREATE UNIQUE INDEX IF NOT EXISTS uzytkownicy_email_uniq ON uzytkownicy (email) WHERE email IS NOT NULL;

CREATE TABLE IF NOT EXISTS tokeny_weryfikacji (
    id               SERIAL PRIMARY KEY,
    token            TEXT UNIQUE NOT NULL,
    uzytkownik_id    INT REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    data_wygasniecia TIMESTAMPTZ NOT NULL
);

-- Migracja: zagnieżdżone komentarze
ALTER TABLE komentarze ADD COLUMN IF NOT EXISTS rodzic_id INT REFERENCES komentarze(id);

-- Migracja: rola moderatora
ALTER TABLE uzytkownicy ADD COLUMN IF NOT EXISTS jest_moderatorem BOOLEAN NOT NULL DEFAULT FALSE;

-- Migracja: system moderacji wpisów
ALTER TABLE wpisy ADD COLUMN IF NOT EXISTS usunieto BOOLEAN NOT NULL DEFAULT FALSE;

-- Migracja: system moderacji komentarzy
ALTER TABLE komentarze ADD COLUMN IF NOT EXISTS usunieto BOOLEAN NOT NULL DEFAULT FALSE;

-- Migracja: indeksy dla wydajności
CREATE INDEX IF NOT EXISTS wpisy_autor_id_idx ON wpisy (autor_id);
CREATE INDEX IF NOT EXISTS wpisy_data_dodania_idx ON wpisy (data_dodania DESC);

CREATE INDEX IF NOT EXISTS komentarze_wpis_id_idx ON komentarze (wpis_id);
CREATE INDEX IF NOT EXISTS komentarze_autor_id_idx ON komentarze (autor_id);
CREATE INDEX IF NOT EXISTS komentarze_rodzic_id_idx ON komentarze (rodzic_id);

CREATE INDEX IF NOT EXISTS glosy_wpis_id_idx ON glosy (wpis_id);
CREATE INDEX IF NOT EXISTS glosy_komentarz_id_idx ON glosy (komentarz_id);

CREATE VIEW wpisy_z_wynikiem AS
SELECT
    w.id,
    w.tytul,
    w.tresc,
    w.link,
    w.autor_id,
    w.data_dodania,
    COALESCE(SUM(g.wartosc), 0) AS wynik,
    w.rodzaj,
    w.usunieto
FROM wpisy w
LEFT JOIN glosy g ON g.wpis_id = w.id
GROUP BY w.id, w.tytul, w.tresc, w.link, w.autor_id, w.data_dodania, w.rodzaj, w.usunieto;

-- Migracja: blokada głosowania na własne wpisy i komentarze
CREATE OR REPLACE FUNCTION dodaj_glos(p_uzytkownik_id INT, p_wpis_id INT, p_wartosc SMALLINT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM wpisy WHERE id = p_wpis_id AND autor_id = p_uzytkownik_id) THEN
        RETURN;
    END IF;
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

CREATE OR REPLACE FUNCTION dodaj_glos_komentarz(p_uzytkownik_id INT, p_komentarz_id INT, p_wartosc SMALLINT)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM komentarze WHERE id = p_komentarz_id AND autor_id = p_uzytkownik_id) THEN
        RETURN;
    END IF;
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

