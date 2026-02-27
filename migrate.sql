-- Migracja uprawnień rejestracji użytkownika
-- Uruchom na istniejącej bazie, jeśli rejestracja zwraca "permission denied for table uzytkownicy"

ALTER FUNCTION zarejestruj_uzytkownika(TEXT, TEXT)
    SECURITY DEFINER
    SET search_path = public, pg_temp;
