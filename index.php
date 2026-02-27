<?php

define('BAZA_HOST',       getenv('BAZA_HOST')       ?: 'localhost');
define('BAZA_NAZWA',      getenv('BAZA_NAZWA')      ?: 'mirkovibe');
define('BAZA_UZYTKOWNIK', getenv('BAZA_UZYTKOWNIK') ?: 'postgres');
define('BAZA_HASLO',      getenv('BAZA_HASLO')      ?: '');

try {
    $polaczenie = new PDO(
        'pgsql:host=' . BAZA_HOST . ';dbname=' . BAZA_NAZWA,
        BAZA_UZYTKOWNIK,
        BAZA_HASLO
    );
    $polaczenie->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $blad) {
    error_log('Błąd połączenia z bazą danych: ' . $blad->getMessage());
    die('Błąd połączenia z bazą danych.');
}
