CREATE TABLE uzytkownicy (
    id               SERIAL PRIMARY KEY,
    nazwa            TEXT UNIQUE NOT NULL,
    haslo_hash       TEXT NOT NULL,
    data_rejestracji TIMESTAMPTZ DEFAULT NOW()
);
