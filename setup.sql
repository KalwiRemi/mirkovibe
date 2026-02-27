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
