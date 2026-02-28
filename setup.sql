CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE uzytkownicy (
    id                   SERIAL PRIMARY KEY,
    nazwa                TEXT UNIQUE NOT NULL,
    haslo_hash           TEXT NOT NULL,
    email                TEXT UNIQUE,
    email_zweryfikowany  BOOLEAN NOT NULL DEFAULT FALSE,
    jest_adminem         BOOLEAN NOT NULL DEFAULT FALSE,
    jest_moderatorem     BOOLEAN NOT NULL DEFAULT FALSE,
    data_rejestracji     TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE konfiguracja (
    klucz   TEXT PRIMARY KEY,
    wartosc TEXT NOT NULL
);

INSERT INTO konfiguracja (klucz, wartosc) VALUES ('rejestracja_wlaczona', 'false');
INSERT INTO konfiguracja (klucz, wartosc) VALUES ('minimalny_czas_wpisu', '12');
INSERT INTO konfiguracja (klucz, wartosc) VALUES ('minimalny_czas_komentarza', '1');

CREATE TABLE wpisy (
    id           SERIAL PRIMARY KEY,
    tytul        TEXT,
    tresc        TEXT,
    link         TEXT,
    rodzaj       TEXT NOT NULL DEFAULT 'wpis',
    autor_id     INT REFERENCES uzytkownicy(id),
    data_dodania TIMESTAMPTZ DEFAULT NOW(),
    wynik        INT DEFAULT 0,
    usunieto     BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE komentarze (
    id           SERIAL PRIMARY KEY,
    wpis_id      INT REFERENCES wpisy(id),
    autor_id     INT REFERENCES uzytkownicy(id),
    tresc        TEXT NOT NULL,
    data_dodania TIMESTAMPTZ DEFAULT NOW(),
    wynik        INT DEFAULT 0,
    rodzic_id    INT REFERENCES komentarze(id),
    usunieto     BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE tokeny_weryfikacji (
    id               SERIAL PRIMARY KEY,
    token            TEXT UNIQUE NOT NULL,
    uzytkownik_id    INT REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    data_wygasniecia TIMESTAMPTZ NOT NULL
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
    COALESCE(SUM(g.wartosc), 0) AS wynik,
    w.rodzaj,
    w.usunieto
FROM wpisy w
LEFT JOIN glosy g ON g.wpis_id = w.id
GROUP BY w.id, w.tytul, w.tresc, w.link, w.autor_id, w.data_dodania, w.rodzaj, w.usunieto;

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
