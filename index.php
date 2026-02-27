<?php

session_start();

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

$dozwolone_strony = ['glowna', 'wpis', 'dodaj', 'logowanie', 'rejestracja', 'wyloguj', 'dodaj_komentarz', 'glosuj'];

function renderujKomentarze(array $komentarze, int $wpis_id, bool $zalogowany, string $blad = ''): string {
    $html  = '<div id="komentarze-sekcja">';
    $html .= '<section style="margin-top:2rem;">';
    $html .= '<h2 style="margin-bottom:1rem;">Komentarze (' . count($komentarze) . ')</h2>';

    if (empty($komentarze)) {
        $html .= '<p>Brak komentarzy.</p>';
    } else {
        $html .= '<ul style="list-style:none;display:flex;flex-direction:column;gap:0.75rem;">';
        foreach ($komentarze as $komentarz) {
            $k_autor = htmlspecialchars($komentarz['autor'], ENT_QUOTES, 'UTF-8');
            $k_tresc = htmlspecialchars($komentarz['tresc'], ENT_QUOTES, 'UTF-8');
            $k_data  = htmlspecialchars(date('d.m.Y H:i', strtotime($komentarz['data_dodania'])), ENT_QUOTES, 'UTF-8');
            $html .= '<li style="background:#fff;border-radius:6px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,.1);">';
            $html .= '<div style="font-size:0.85rem;color:#555;margin-bottom:0.4rem;">';
            $html .= '<strong>' . $k_autor . '</strong> &nbsp;|&nbsp; ' . $k_data;
            $html .= '</div>';
            $html .= '<div>' . nl2br($k_tresc) . '</div>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '</section>';

    if ($zalogowany) {
        $akcja = htmlspecialchars('index.php?strona=dodaj_komentarz&id=' . $wpis_id, ENT_QUOTES, 'UTF-8');
        $html .= '<form method="post" style="margin-top:1.5rem;display:flex;flex-direction:column;gap:0.75rem;"'
               . ' hx-post="' . $akcja . '" hx-target="#komentarze-sekcja" hx-swap="outerHTML">';
        if ($blad !== '') {
            $html .= '<p style="color:red;">' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $html .= '<textarea name="tresc" placeholder="Dodaj komentarz..." rows="3" maxlength="2000" style="resize:vertical;" required></textarea>';
        $html .= '<button type="submit" style="align-self:flex-start;">Wyślij komentarz</button>';
        $html .= '</form>';
    }

    $html .= '</div>';
    return $html;
}
$zadana_strona = isset($_GET['strona']) ? htmlspecialchars($_GET['strona'], ENT_QUOTES, 'UTF-8') : '';
$strona = in_array($zadana_strona, $dozwolone_strony) ? $zadana_strona : 'glowna';

ob_start();
switch ($strona) {
    case 'glowna':
        $wpisow_na_strone = 10;
        $podstrona = max(1, (int)($_GET['podstrona'] ?? 1));
        $przesuniecie = ($podstrona - 1) * $wpisow_na_strone;

        try {
            $stmt_licznik = $polaczenie->query('SELECT COUNT(*) FROM wpisy_z_wynikiem');
            $liczba_wpisow = (int)$stmt_licznik->fetchColumn();

            $stmt = $polaczenie->prepare(
                'SELECT w.id, w.tytul, u.nazwa AS autor, w.wynik, w.data_dodania,
                        COUNT(k.id) AS liczba_komentarzy
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 LEFT JOIN komentarze k ON k.wpis_id = w.id
                 GROUP BY w.id, w.tytul, u.nazwa, w.wynik, w.data_dodania
                 ORDER BY w.data_dodania DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit',  $wpisow_na_strone, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $przesuniecie,     PDO::PARAM_INT);
            $stmt->execute();
            $wpisy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania wpisów: ' . $e->getMessage());
            $wpisy = [];
            $liczba_wpisow = 0;
        }

        $liczba_stron = (int)ceil($liczba_wpisow / $wpisow_na_strone);

        echo '<h1>Lista wpisów</h1>';

        if (empty($wpisy)) {
            echo '<p>Brak wpisów.</p>';
        } else {
            echo '<ul style="list-style:none;display:flex;flex-direction:column;gap:1rem;margin-top:1rem;">';
            foreach ($wpisy as $wpis) {
                $tytul = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
                $autor = htmlspecialchars($wpis['autor'],        ENT_QUOTES, 'UTF-8');
                $wynik = (int)$wpis['wynik'];
                $komentarze = (int)$wpis['liczba_komentarzy'];
                $data = htmlspecialchars(
                    date('d.m.Y H:i', strtotime($wpis['data_dodania'])),
                    ENT_QUOTES, 'UTF-8'
                );
                $id = (int)$wpis['id'];
                echo '<li style="background:#fff;border-radius:6px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,.1);">';
                echo '<a href="index.php?strona=wpis&amp;id=' . $id . '" style="font-size:1.1rem;font-weight:bold;text-decoration:none;color:#1a1a2e;">' . $tytul . '</a>';
                echo '<div style="margin-top:0.4rem;font-size:0.85rem;color:#555;">';
                echo 'Autor: <strong>' . $autor . '</strong> &nbsp;|&nbsp; ';
                echo 'Wynik: <span id="wynik-wpisu-' . $id . '"><strong>' . $wynik . '</strong></span>';
                if (isset($_SESSION['uzytkownik_id'])) {
                    echo ' <button type="button" hx-post="index.php?strona=glosuj" hx-target="#wynik-wpisu-' . $id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $id . '","wartosc":"1"}\' aria-label="Zagłosuj za" style="cursor:pointer;padding:0 6px;border:1px solid #ccc;border-radius:3px;background:#e8f5e9;">+</button>';
                    echo ' <button type="button" hx-post="index.php?strona=glosuj" hx-target="#wynik-wpisu-' . $id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw" style="cursor:pointer;padding:0 6px;border:1px solid #ccc;border-radius:3px;background:#ffebee;">−</button>';
                }
                echo ' &nbsp;|&nbsp; ';
                echo 'Komentarze: <strong>' . $komentarze . '</strong> &nbsp;|&nbsp; ';
                echo $data;
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }

        if ($liczba_stron > 1) {
            echo '<nav style="margin-top:1.5rem;display:flex;gap:0.5rem;align-items:center;">';
            for ($i = 1; $i <= $liczba_stron; $i++) {
                $aktywna = $i === $podstrona ? 'font-weight:bold;text-decoration:underline;' : '';
                echo '<a href="index.php?strona=glowna&amp;podstrona=' . $i . '" style="' . $aktywna . '">' . $i . '</a>';
            }
            echo '</nav>';
        }
        break;
    case 'wpis':
        $wpis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($wpis_id <= 0) {
            echo '<p>Nieprawidłowy identyfikator wpisu.</p>';
            break;
        }

        try {
            $stmt = $polaczenie->prepare(
                'SELECT w.id, w.tytul, w.tresc, w.link, u.nazwa AS autor, w.wynik, w.data_dodania
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 WHERE w.id = :id'
            );
            $stmt->execute([':id' => $wpis_id]);
            $wpis = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania wpisu: ' . $e->getMessage());
            $wpis = null;
        }

        if (!$wpis) {
            echo '<p>Nie znaleziono wpisu.</p>';
            break;
        }

        $tytul = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
        $autor = htmlspecialchars($wpis['autor'], ENT_QUOTES, 'UTF-8');
        $wynik = (int)$wpis['wynik'];
        $data  = htmlspecialchars(date('d.m.Y H:i', strtotime($wpis['data_dodania'])), ENT_QUOTES, 'UTF-8');

        echo '<article style="background:#fff;border-radius:6px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.1);">';
        echo '<h1 style="margin-bottom:0.75rem;">' . $tytul . '</h1>';

        if (!empty($wpis['tresc'])) {
            echo '<div style="margin-bottom:1rem;line-height:1.6;">' . nl2br(htmlspecialchars($wpis['tresc'], ENT_QUOTES, 'UTF-8')) . '</div>';
        }
        if (!empty($wpis['link'])) {
            $link_raw = $wpis['link'];
            $link_schema = strtolower(parse_url($link_raw, PHP_URL_SCHEME));
            if (in_array($link_schema, ['http', 'https'], true)) {
                $link = htmlspecialchars($link_raw, ENT_QUOTES, 'UTF-8');
                echo '<p style="margin-bottom:1rem;"><a href="' . $link . '" rel="noopener noreferrer" target="_blank">' . $link . '</a></p>';
            }
        }

        echo '<p style="font-size:0.85rem;color:#555;">';
        echo 'Autor: <strong>' . $autor . '</strong> &nbsp;|&nbsp; ';
        echo 'Wynik: <span id="wynik-wpisu-' . $wpis_id . '"><strong>' . $wynik . '</strong></span>';
        if (isset($_SESSION['uzytkownik_id'])) {
            echo ' <button type="button" hx-post="index.php?strona=glosuj" hx-target="#wynik-wpisu-' . $wpis_id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $wpis_id . '","wartosc":"1"}\' aria-label="Zagłosuj za" style="cursor:pointer;padding:0 6px;border:1px solid #ccc;border-radius:3px;background:#e8f5e9;">+</button>';
            echo ' <button type="button" hx-post="index.php?strona=glosuj" hx-target="#wynik-wpisu-' . $wpis_id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $wpis_id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw" style="cursor:pointer;padding:0 6px;border:1px solid #ccc;border-radius:3px;background:#ffebee;">−</button>';
        }
        echo ' &nbsp;|&nbsp; ';
        echo $data;
        echo '</p>';
        echo '</article>';

        try {
            $stmt = $polaczenie->prepare(
                'SELECT k.id, k.tresc, u.nazwa AS autor, k.data_dodania
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 WHERE k.wpis_id = :wpis_id
                 ORDER BY k.data_dodania ASC'
            );
            $stmt->execute([':wpis_id' => $wpis_id]);
            $komentarze = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania komentarzy: ' . $e->getMessage());
            $komentarze = [];
        }

        echo renderujKomentarze($komentarze, $wpis_id, isset($_SESSION['uzytkownik_id']));
        break;
    case 'dodaj':
        if (!isset($_SESSION['uzytkownik_id'])) {
            header('Location: index.php?strona=logowanie');
            exit;
        }

        $bledy = [];
        $tytul_wpisany = '';
        $tresc_wpisana = '';
        $link_wpisany  = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tytul_wpisany = trim($_POST['tytul'] ?? '');
            $tresc_wpisana = trim($_POST['tresc'] ?? '');
            $link_wpisany  = trim($_POST['link']  ?? '');

            if ($tytul_wpisany === '') {
                $bledy[] = 'Tytuł jest wymagany.';
            }
            if ($tresc_wpisana === '' && $link_wpisany === '') {
                $bledy[] = 'Podaj treść lub link.';
            }

            if (empty($bledy)) {
                try {
                    $stmt = $polaczenie->prepare(
                        'INSERT INTO wpisy (tytul, tresc, link, autor_id)
                         VALUES (:tytul, :tresc, :link, :autor_id)
                         RETURNING id'
                    );
                    $stmt->execute([
                        ':tytul'    => $tytul_wpisany,
                        ':tresc'    => $tresc_wpisana ?: null,
                        ':link'     => $link_wpisany  ?: null,
                        ':autor_id' => $_SESSION['uzytkownik_id'],
                    ]);
                    $nowy_id = (int)$stmt->fetchColumn();
                    header('Location: index.php?strona=wpis&id=' . $nowy_id);
                    exit;
                } catch (PDOException $e) {
                    error_log('Błąd dodawania wpisu: ' . $e->getMessage());
                    $bledy[] = 'Wystąpił błąd podczas dodawania wpisu. Spróbuj ponownie.';
                }
            }
        }

        echo '<h1>Dodaj wpis</h1>';
        if (!empty($bledy)) {
            echo '<ul style="color:red;margin-bottom:1rem;">';
            foreach ($bledy as $blad) {
                echo '<li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        echo '<form method="post" style="display:flex;flex-direction:column;gap:0.75rem;max-width:600px;">';
        echo '<input type="text" name="tytul" placeholder="Tytuł" value="' . htmlspecialchars($tytul_wpisany, ENT_QUOTES, 'UTF-8') . '" required>';
        echo '<textarea name="tresc" placeholder="Treść (opcjonalnie)" rows="5" style="resize:vertical;">' . htmlspecialchars($tresc_wpisana, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '<input type="url" name="link" placeholder="Link (opcjonalnie)" value="' . htmlspecialchars($link_wpisany, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button type="submit">Dodaj wpis</button>';
        echo '</form>';
        break;
    case 'logowanie':
        if (isset($_SESSION['uzytkownik_id'])) {
            header('Location: index.php?strona=glowna');
            exit;
        }

        $bledy = [];
        $nazwa_wpisana = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nazwa_wpisana = trim($_POST['nazwa'] ?? '');
            $haslo         = $_POST['haslo'] ?? '';

            if ($nazwa_wpisana === '') {
                $bledy[] = 'Podaj nazwę użytkownika.';
            }
            if ($haslo === '') {
                $bledy[] = 'Podaj hasło.';
            }

            if (empty($bledy)) {
                try {
                    $stmt = $polaczenie->prepare('SELECT id, nazwa, haslo_hash FROM uzytkownicy WHERE nazwa = :nazwa');
                    $stmt->execute([':nazwa' => $nazwa_wpisana]);
                    $uzytkownik = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($uzytkownik && password_verify($haslo, $uzytkownik['haslo_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['uzytkownik_id']   = $uzytkownik['id'];
                        $_SESSION['uzytkownik_nazwa'] = $uzytkownik['nazwa'];
                        header('Location: index.php?strona=glowna');
                        exit;
                    } else {
                        $bledy[] = 'Nieprawidłowa nazwa użytkownika lub hasło.';
                    }
                } catch (PDOException $e) {
                    error_log('Błąd logowania: ' . $e->getMessage());
                    $bledy[] = 'Wystąpił błąd podczas logowania. Spróbuj ponownie.';
                }
            }
        }

        echo '<h1>Logowanie</h1>';
        if (!empty($bledy)) {
            echo '<ul style="color:red;margin-bottom:1rem;">';
            foreach ($bledy as $blad) {
                echo '<li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        echo '<form method="post" style="display:flex;flex-direction:column;gap:0.75rem;max-width:360px;">';
        echo '<input type="text" name="nazwa" placeholder="Nazwa użytkownika" value="' . htmlspecialchars($nazwa_wpisana, ENT_QUOTES, 'UTF-8') . '" required>';
        echo '<input type="password" name="haslo" placeholder="Hasło" required>';
        echo '<button type="submit">Zaloguj się</button>';
        echo '</form>';
        echo '<p style="margin-top:1rem;">Nie masz konta? <a href="index.php?strona=rejestracja">Zarejestruj się</a></p>';
        break;
    case 'rejestracja':
        $bledy = [];
        $nazwa_wpisana = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nazwa_wpisana = trim($_POST['nazwa'] ?? '');
            $haslo         = $_POST['haslo']  ?? '';
            $haslo2        = $_POST['haslo2'] ?? '';

            if (strlen($nazwa_wpisana) < 3) {
                $bledy[] = 'Nazwa użytkownika musi mieć co najmniej 3 znaki.';
            }
            if (strlen($haslo) < 6) {
                $bledy[] = 'Hasło musi mieć co najmniej 6 znaków.';
            }
            if ($haslo !== $haslo2) {
                $bledy[] = 'Hasła nie są zgodne.';
            }

            if (empty($bledy)) {
                try {
                    // Hashing is handled inside the SQL function via pgcrypto crypt()
                    $stmt = $polaczenie->prepare('SELECT zarejestruj_uzytkownika(:nazwa, :haslo)');
                    $stmt->execute([':nazwa' => $nazwa_wpisana, ':haslo' => $haslo]);
                    header('Location: index.php?strona=logowanie');
                    exit;
                } catch (PDOException $e) {
                    // The SQL function re-raises unique_violation as P0001 via RAISE EXCEPTION
                    if ($e->getCode() === '23505' || $e->getCode() === 'P0001') {
                        $bledy[] = 'Użytkownik o podanej nazwie już istnieje.';
                    } else {
                        error_log('Błąd rejestracji: ' . $e->getMessage());
                        $bledy[] = 'Wystąpił błąd podczas rejestracji. Spróbuj ponownie.';
                    }
                }
            }
        }

        echo '<h1>Rejestracja</h1>';
        if (!empty($bledy)) {
            echo '<ul style="color:red;margin-bottom:1rem;">';
            foreach ($bledy as $blad) {
                echo '<li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        echo '<form method="post" style="display:flex;flex-direction:column;gap:0.75rem;max-width:360px;">';
        echo '<input type="text" name="nazwa" placeholder="Nazwa użytkownika" value="' . htmlspecialchars($nazwa_wpisana, ENT_QUOTES, 'UTF-8') . '" required>';
        echo '<input type="password" name="haslo" placeholder="Hasło (min. 6 znaków)" required>';
        echo '<input type="password" name="haslo2" placeholder="Powtórz hasło" required>';
        echo '<button type="submit">Zarejestruj się</button>';
        echo '</form>';
        break;
    case 'dodaj_komentarz':
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!isset($_SESSION['uzytkownik_id'])) {
            http_response_code(403);
            echo '<p style="color:red;">Musisz być zalogowany, aby dodać komentarz.</p>';
            exit;
        }

        $wpis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($wpis_id <= 0) {
            http_response_code(400);
            echo '<p style="color:red;">Nieprawidłowy identyfikator wpisu.</p>';
            exit;
        }

        $blad_komentarza   = '';
        $tresc_komentarza  = trim($_POST['tresc'] ?? '');
        if (!empty($tresc_komentarza)) {
            if (strlen($tresc_komentarza) > 2000) {
                $blad_komentarza = 'Komentarz nie może przekraczać 2000 znaków.';
            } else {
                try {
                    $stmt = $polaczenie->prepare(
                        'INSERT INTO komentarze (wpis_id, autor_id, tresc)
                         VALUES (:wpis_id, :autor_id, :tresc)'
                    );
                    $stmt->execute([
                        ':wpis_id'  => $wpis_id,
                        ':autor_id' => $_SESSION['uzytkownik_id'],
                        ':tresc'    => $tresc_komentarza,
                    ]);
                } catch (PDOException $e) {
                    error_log('Błąd dodawania komentarza: ' . $e->getMessage());
                    $blad_komentarza = 'Wystąpił błąd podczas dodawania komentarza. Spróbuj ponownie.';
                }
            }
        }

        try {
            $stmt = $polaczenie->prepare(
                'SELECT k.id, k.tresc, u.nazwa AS autor, k.data_dodania
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 WHERE k.wpis_id = :wpis_id
                 ORDER BY k.data_dodania ASC'
            );
            $stmt->execute([':wpis_id' => $wpis_id]);
            $komentarze = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania komentarzy: ' . $e->getMessage());
            $komentarze = [];
        }

        echo renderujKomentarze($komentarze, $wpis_id, true, $blad_komentarza);
        exit;
    case 'glosuj':
        ob_end_clean();
        if (!isset($_SESSION['uzytkownik_id'])) {
            http_response_code(403);
            exit;
        }

        $wpis_id = isset($_POST['wpis_id']) ? (int)$_POST['wpis_id'] : 0;
        $wartosc  = isset($_POST['wartosc'])  ? (int)$_POST['wartosc']  : 0;

        if ($wpis_id <= 0 || !in_array($wartosc, [1, -1], true)) {
            http_response_code(400);
            exit;
        }

        try {
            $stmt = $polaczenie->prepare('SELECT dodaj_glos(:uzytkownik_id, :wpis_id, CAST(:wartosc AS SMALLINT))');
            $stmt->bindValue(':uzytkownik_id', $_SESSION['uzytkownik_id'], PDO::PARAM_INT);
            $stmt->bindValue(':wpis_id',       $wpis_id,                   PDO::PARAM_INT);
            $stmt->bindValue(':wartosc',       $wartosc,                   PDO::PARAM_INT);
            $stmt->execute();

            $stmt2 = $polaczenie->prepare('SELECT wynik FROM wpisy_z_wynikiem WHERE id = :id');
            $stmt2->bindValue(':id', $wpis_id, PDO::PARAM_INT);
            $stmt2->execute();
            $wynik_raw = $stmt2->fetchColumn();

            if ($wynik_raw === false) {
                http_response_code(404);
                exit;
            }

            echo '<strong>' . (int)$wynik_raw . '</strong>';
        } catch (PDOException $e) {
            error_log('Błąd głosowania: ' . $e->getMessage());
            http_response_code(500);
        }
        exit;
    case 'wyloguj':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        header('Location: index.php?strona=logowanie');
        exit;
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
            <?php if (isset($_SESSION['uzytkownik_id'])): ?>
                <span style="color:#ccc;font-size:0.95rem;">Witaj, <?= htmlspecialchars($_SESSION['uzytkownik_nazwa'], ENT_QUOTES, 'UTF-8') ?>!</span>
                <a href="index.php?strona=wyloguj">Wyloguj</a>
            <?php else: ?>
                <a href="index.php?strona=logowanie">Logowanie</a>
                <a href="index.php?strona=rejestracja">Rejestracja</a>
            <?php endif; ?>
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
