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

header('Content-Type: text/html; charset=UTF-8');

$dozwolone_strony = ['glowna', 'wpis', 'dodaj', 'logowanie', 'rejestracja', 'wyloguj'];
$zadana_strona = isset($_GET['strona']) ? htmlspecialchars($_GET['strona'], ENT_QUOTES, 'UTF-8') : '';
$strona = in_array($zadana_strona, $dozwolone_strony) ? $zadana_strona : 'glowna';

ob_start();
switch ($strona) {
    case 'glowna':
        echo '<h1>Lista wpisów</h1>';
        break;
    case 'wpis':
        echo '<h1>Wpis</h1>';
        break;
    case 'dodaj':
        echo '<h1>Dodaj wpis</h1>';
        break;
    case 'logowanie':
        echo '<h1>Logowanie</h1>';
        break;
    case 'rejestracja':
        echo '<h1>Rejestracja</h1>';
        break;
    case 'wyloguj':
        echo '<h1>Wylogowano</h1>';
        break;
}
$tresc = ob_get_clean();

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirkovibe</title>
    <script src="https://unpkg.com/htmx.org" defer></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f4f4; color: #222; display: flex; flex-direction: column; min-height: 100vh; }
        header { background: #1a1a2e; color: #fff; padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 2rem; }
        header .nazwa-serwisu { font-size: 1.4rem; font-weight: bold; text-decoration: none; color: #fff; }
        nav a { color: #ccc; text-decoration: none; margin-right: 1rem; font-size: 0.95rem; }
        nav a:hover { color: #fff; }
        main { flex: 1; max-width: 900px; width: 100%; margin: 2rem auto; padding: 0 1rem; }
        footer { background: #1a1a2e; color: #aaa; text-align: center; padding: 0.75rem 1.5rem; font-size: 0.85rem; }
    </style>
</head>
<body>
    <header>
        <a class="nazwa-serwisu" href="index.php">Mirkovibe</a>
        <nav>
            <a href="index.php?strona=glowna">Główna</a>
            <a href="index.php?strona=dodaj">Dodaj wpis</a>
            <a href="index.php?strona=logowanie">Logowanie</a>
            <a href="index.php?strona=rejestracja">Rejestracja</a>
        </nav>
    </header>
    <main>
        <?= $tresc ?>
    </main>
    <footer>
        &copy; <?= date('Y') ?> Mirkovibe
    </footer>
</body>
</html>
