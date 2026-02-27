CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE uzytkownicy (
    id               SERIAL PRIMARY KEY,
    nazwa            TEXT UNIQUE NOT NULL,
    haslo_hash       TEXT NOT NULL,
    data_rejestracji TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE wpisy (
    id           SERIAL PRIMARY KEY,
    tytul        TEXT,
    tresc        TEXT,
    link         TEXT,
    autor_id     INT REFERENCES uzytkownicy(id),
    data_dodania TIMESTAMPTZ DEFAULT NOW(),
    wynik        INT DEFAULT 0
);

CREATE TABLE komentarze (
    id           SERIAL PRIMARY KEY,
    wpis_id      INT REFERENCES wpisy(id),
    autor_id     INT REFERENCES uzytkownicy(id),
    tresc        TEXT NOT NULL,
    data_dodania TIMESTAMPTZ DEFAULT NOW(),
    wynik        INT DEFAULT 0
);

CREATE TABLE glosy (
    id             SERIAL PRIMARY KEY,
    uzytkownik_id  INT REFERENCES uzytkownicy(id),
    wpis_id        INT REFERENCES wpisy(id),
    komentarz_id   INT REFERENCES komentarze(id),
    wartosc        SMALLINT NOT NULL CHECK (wartosc IN (1, -1)),
    UNIQUE (uzytkownik_id, wpis_id),
    UNIQUE (uzytkownik_id, komentarz_id)
);

CREATE VIEW wpisy_z_wynikiem AS
SELECT
    w.id,
    w.tytul,
    w.tresc,
    w.link,
    w.autor_id,
    w.data_dodania,
    COALESCE(SUM(g.wartosc), 0) AS wynik
FROM wpisy w
LEFT JOIN glosy g ON g.wpis_id = w.id
GROUP BY w.id, w.tytul, w.tresc, w.link, w.autor_id, w.data_dodania;

CREATE FUNCTION dodaj_glos(p_uzytkownik_id INT, p_wpis_id INT, p_wartosc SMALLINT)
RETURNS VOID AS $$
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
$$ LANGUAGE plpgsql;

CREATE FUNCTION dodaj_glos_komentarz(p_uzytkownik_id INT, p_komentarz_id INT, p_wartosc SMALLINT)
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

CREATE FUNCTION zarejestruj_uzytkownika(p_nazwa TEXT, p_haslo TEXT)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    INSERT INTO uzytkownicy (nazwa, haslo_hash)
    VALUES (p_nazwa, crypt(p_haslo, gen_salt('bf')));
EXCEPTION
    WHEN unique_violation THEN
        RAISE EXCEPTION 'Użytkownik o nazwie "%" już istnieje.', p_nazwa;
END;
$$;
