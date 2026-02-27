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

$dozwolone_strony = ['glowna', 'wpis', 'dodaj', 'logowanie', 'rejestracja', 'wyloguj', 'dodaj_komentarz', 'glosuj', 'glosuj_komentarz', 'tag'];

function renderujKomentarze(array $komentarze, int $wpis_id, bool $zalogowany, string $blad = ''): string {
    $html  = '<div id="komentarze-sekcja">';
    $html .= '<section class="comments-section">';
    $html .= '<h2>Komentarze (' . count($komentarze) . ')</h2>';

    if (empty($komentarze)) {
        $html .= '<p class="empty-state">Brak komentarzy.</p>';
    } else {
        $html .= '<ul class="comment-list">';
        foreach ($komentarze as $komentarz) {
            $k_id    = (int)$komentarz['id'];
            $k_autor = htmlspecialchars($komentarz['autor'], ENT_QUOTES, 'UTF-8');
            $k_tresc = htmlspecialchars($komentarz['tresc'], ENT_QUOTES, 'UTF-8');
            $k_data  = htmlspecialchars(date('d.m.Y H:i', strtotime($komentarz['data_dodania'])), ENT_QUOTES, 'UTF-8');
            $k_wynik = (int)($komentarz['wynik'] ?? 0);
            $html .= '<li class="comment-item">';
            $html .= '<div class="comment-meta">';
            $html .= '<strong>' . $k_autor . '</strong>';
            $html .= '<span class="sep">|</span>' . $k_data;
            $html .= '<span class="sep">|</span> Wynik: <span id="wynik-komentarza-' . $k_id . '" class="score">' . $k_wynik . '</span>';
            if ($zalogowany) {
                $html .= ' <button type="button" class="btn-vote up" hx-post="/glosuj_komentarz" hx-target="#wynik-komentarza-' . $k_id . '" hx-swap="innerHTML" hx-vals=\'{"komentarz_id":"' . $k_id . '","wartosc":"1"}\' aria-label="Zagłosuj za">+</button>';
                $html .= ' <button type="button" class="btn-vote down" hx-post="/glosuj_komentarz" hx-target="#wynik-komentarza-' . $k_id . '" hx-swap="innerHTML" hx-vals=\'{"komentarz_id":"' . $k_id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw">−</button>';
            }
            $html .= '</div>';
            $html .= '<div>' . nl2br(parsujTagi($k_tresc)) . '</div>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '</section>';

    if ($zalogowany) {
        $akcja = '/dodaj_komentarz/' . $wpis_id;
        $html .= '<form method="post" class="form-stack form-stack--comment"'
               . ' hx-post="' . $akcja . '" hx-target="#komentarze-sekcja" hx-swap="outerHTML">';
        if ($blad !== '') {
            $html .= '<ul class="error-list"><li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li></ul>';
        }
        $html .= '<textarea name="tresc" placeholder="Dodaj komentarz..." rows="3" maxlength="2000" required></textarea>';
        $html .= '<button type="submit" class="btn-primary">Wyślij komentarz</button>';
        $html .= '</form>';
    }

    $html .= '</div>';
    return $html;
}
function parsujTagi(string $tekst): string {
    return preg_replace_callback(
        '/(^|[^\p{L}\p{N}_#])#([\p{L}\p{N}_]+)/u',
        function ($m) {
            $tag = $m[2];
            return $m[1] . '<a href="/tag/' . rawurlencode($tag) . '" class="tag-link">#' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</a>';
        },
        $tekst
    );
}

if (!isset($_GET['strona'])) {
    $uri    = strtok($_SERVER['REQUEST_URI'], '?');
    $uri    = trim($uri, '/');
    $czesci = $uri === '' ? [] : explode('/', $uri);
    $seg0   = $czesci[0] ?? '';
    $seg1   = isset($czesci[1]) && $czesci[1] !== '' ? $czesci[1] : null;
    switch ($seg0) {
        case 'wpis':
            $_GET['strona'] = 'wpis';
            if ($seg1 !== null && ctype_digit($seg1)) {
                $_GET['id'] = $seg1;
            }
            break;
        case 'dodaj':
            $_GET['strona'] = 'dodaj';
            break;
        case 'logowanie':
            $_GET['strona'] = 'logowanie';
            break;
        case 'rejestracja':
            $_GET['strona'] = 'rejestracja';
            break;
        case 'wyloguj':
            $_GET['strona'] = 'wyloguj';
            break;
        case 'dodaj_komentarz':
            $_GET['strona'] = 'dodaj_komentarz';
            if ($seg1 !== null && ctype_digit($seg1)) {
                $_GET['id'] = $seg1;
            }
            break;
        case 'glosuj':
            $_GET['strona'] = 'glosuj';
            break;
        case 'glosuj_komentarz':
            $_GET['strona'] = 'glosuj_komentarz';
            break;
        case 'tag':
            $_GET['strona'] = 'tag';
            if ($seg1 !== null && preg_match('/^[\p{L}\p{N}_]+$/u', $seg1)) {
                $_GET['tag'] = $seg1;
            }
            break;
        default:
            $_GET['strona'] = 'glowna';
            if ($seg1 !== null && ctype_digit($seg1)) {
                $_GET['podstrona'] = $seg1;
            }
            break;
    }
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
                'SELECT w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa AS autor, w.wynik, w.data_dodania,
                        COUNT(k.id) AS liczba_komentarzy
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 LEFT JOIN komentarze k ON k.wpis_id = w.id
                 GROUP BY w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa, w.wynik, w.data_dodania
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
            echo '<ul class="card-list">';
            foreach ($wpisy as $wpis) {
                $rodzaj     = $wpis['rodzaj'] ?? 'wpis';
                $autor      = htmlspecialchars($wpis['autor'],        ENT_QUOTES, 'UTF-8');
                $wynik      = (int)$wpis['wynik'];
                $komentarze = (int)$wpis['liczba_komentarzy'];
                $data       = htmlspecialchars(
                    date('d.m.Y H:i', strtotime($wpis['data_dodania'])),
                    ENT_QUOTES, 'UTF-8'
                );
                $id = (int)$wpis['id'];
                echo '<li class="card">';
                if ($rodzaj === 'link') {
                    $tytul     = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
                    echo '<span class="type-badge type-badge--link">L</span> ';
                    echo '<a href="/wpis/' . $id . '" class="card-title">' . $tytul . '</a>';
                    if (!empty($wpis['link'])) {
                        $link_schema = strtolower(parse_url($wpis['link'], PHP_URL_SCHEME) ?: '');
                        if (in_array($link_schema, ['http', 'https'], true)) {
                            $link_host = parse_url($wpis['link'], PHP_URL_HOST) ?? '';
                            $link_url  = htmlspecialchars($wpis['link'], ENT_QUOTES, 'UTF-8');
                            if ($link_host !== '') {
                                echo ' <a href="' . $link_url . '" class="card-domain" rel="noopener noreferrer" target="_blank">(' . htmlspecialchars($link_host, ENT_QUOTES, 'UTF-8') . ')</a>';
                            }
                        }
                    }
                } else {
                    $podglad = htmlspecialchars(mb_strimwidth($wpis['tresc'] ?? '', 0, 120, '…'), ENT_QUOTES, 'UTF-8');
                    echo '<a href="/wpis/' . $id . '" class="card-title">' . $podglad . '</a>';
                }
                echo '<div class="card-meta">';
                echo 'Autor: <strong>' . $autor . '</strong>';
                echo '<span class="sep">|</span>';
                echo 'Wynik: <span id="wynik-wpisu-' . $id . '" class="score">' . $wynik . '</span>';
                if (isset($_SESSION['uzytkownik_id'])) {
                    echo ' <button type="button" class="btn-vote up" hx-post="/glosuj" hx-target="#wynik-wpisu-' . $id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $id . '","wartosc":"1"}\' aria-label="Zagłosuj za">+</button>';
                    echo ' <button type="button" class="btn-vote down" hx-post="/glosuj" hx-target="#wynik-wpisu-' . $id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw">−</button>';
                }
                echo '<span class="sep">|</span>';
                echo 'Komentarze: <strong>' . $komentarze . '</strong>';
                echo '<span class="sep">|</span>';
                echo $data;
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }

        if ($liczba_stron > 1) {
            echo '<nav class="pagination">';
            for ($i = 1; $i <= $liczba_stron; $i++) {
                $aktywna = $i === $podstrona ? ' active' : '';
                echo '<a href="/glowna/' . $i . '" class="' . $aktywna . '">' . $i . '</a>';
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
                'SELECT w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa AS autor, w.wynik, w.data_dodania
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

        $rodzaj_wpisu = $wpis['rodzaj'] ?? 'wpis';
        $autor = htmlspecialchars($wpis['autor'], ENT_QUOTES, 'UTF-8');
        $wynik = (int)$wpis['wynik'];
        $data  = htmlspecialchars(date('d.m.Y H:i', strtotime($wpis['data_dodania'])), ENT_QUOTES, 'UTF-8');

        echo '<article class="article-card">';

        if ($rodzaj_wpisu === 'link') {
            $tytul = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
            echo '<h1><span class="type-badge type-badge--link">L</span> ' . $tytul . '</h1>';
            if (!empty($wpis['link'])) {
                $link_raw    = $wpis['link'];
                $link_schema = strtolower(parse_url($link_raw, PHP_URL_SCHEME) ?: '');
                if (in_array($link_schema, ['http', 'https'], true)) {
                    $link = htmlspecialchars($link_raw, ENT_QUOTES, 'UTF-8');
                    echo '<a href="' . $link . '" class="article-link" rel="noopener noreferrer" target="_blank">' . $link . '</a>';
                }
            }
            if (!empty($wpis['tresc'])) {
                echo '<div class="article-body article-tags">' . parsujTagi(htmlspecialchars($wpis['tresc'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }
        } else {
            if (!empty($wpis['tresc'])) {
                echo '<div class="article-body">' . nl2br(parsujTagi(htmlspecialchars($wpis['tresc'], ENT_QUOTES, 'UTF-8'))) . '</div>';
            }
        }

        echo '<div class="card-meta">';
        echo 'Autor: <strong>' . $autor . '</strong>';
        echo '<span class="sep">|</span>';
        echo 'Wynik: <span id="wynik-wpisu-' . $wpis_id . '" class="score">' . $wynik . '</span>';
        if (isset($_SESSION['uzytkownik_id'])) {
            echo ' <button type="button" class="btn-vote up" hx-post="/glosuj" hx-target="#wynik-wpisu-' . $wpis_id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $wpis_id . '","wartosc":"1"}\' aria-label="Zagłosuj za">+</button>';
            echo ' <button type="button" class="btn-vote down" hx-post="/glosuj" hx-target="#wynik-wpisu-' . $wpis_id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $wpis_id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw">−</button>';
        }
        echo '<span class="sep">|</span>';
        echo $data;
        echo '</div>';
        echo '</article>';

        try {
            $stmt = $polaczenie->prepare(
                'SELECT k.id, k.tresc, u.nazwa AS autor, k.data_dodania,
                        COALESCE(SUM(g.wartosc), 0) AS wynik
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 LEFT JOIN glosy g ON g.komentarz_id = k.id
                 WHERE k.wpis_id = :wpis_id
                 GROUP BY k.id, k.tresc, u.nazwa, k.data_dodania
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
            header('Location: /logowanie');
            exit;
        }

        $bledy = [];
        $rodzaj_wpisany = 'wpis';
        $tytul_wpisany  = '';
        $tresc_wpisana  = '';
        $link_wpisany   = '';
        $tagi_wpisane   = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rodzaj_wpisany = in_array($_POST['rodzaj'] ?? '', ['wpis', 'link'], true) ? $_POST['rodzaj'] : 'wpis';
            $tytul_wpisany  = trim($_POST['tytul'] ?? '');
            $tresc_wpisana  = trim($_POST['tresc'] ?? '');
            $link_wpisany   = trim($_POST['link']  ?? '');
            $tagi_wpisane   = trim($_POST['tagi']  ?? '');

            if ($rodzaj_wpisany === 'wpis') {
                if ($tresc_wpisana === '') {
                    $bledy[] = 'Treść jest wymagana.';
                }
            } else {
                if ($tytul_wpisany === '') {
                    $bledy[] = 'Tytuł jest wymagany.';
                }
                if ($link_wpisany === '') {
                    $bledy[] = 'URL jest wymagany.';
                } elseif (!in_array(strtolower(parse_url($link_wpisany, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true)) {
                    $bledy[] = 'URL musi być adresem HTTP lub HTTPS.';
                }
                if ($tagi_wpisane === '') {
                    $bledy[] = 'Tagi są wymagane.';
                }
            }

            if (empty($bledy)) {
                if ($rodzaj_wpisany === 'link') {
                    $fragmenty = preg_split('/[\s,]+/', $tagi_wpisane, -1, PREG_SPLIT_NO_EMPTY);
                    $tresc_do_zapisu = implode(' ', array_map(function ($t) {
                        return '#' . ltrim($t, '#');
                    }, $fragmenty));
                    $tytul_do_zapisu = $tytul_wpisany;
                    $link_do_zapisu  = $link_wpisany;
                } else {
                    $tresc_do_zapisu = $tresc_wpisana;
                    $tytul_do_zapisu = null;
                    $link_do_zapisu  = null;
                }

                try {
                    $stmt = $polaczenie->prepare(
                        'INSERT INTO wpisy (tytul, tresc, link, rodzaj, autor_id)
                         VALUES (:tytul, :tresc, :link, :rodzaj, :autor_id)
                         RETURNING id'
                    );
                    $stmt->execute([
                        ':tytul'    => $tytul_do_zapisu,
                        ':tresc'    => $tresc_do_zapisu ?: null,
                        ':link'     => $link_do_zapisu,
                        ':rodzaj'   => $rodzaj_wpisany,
                        ':autor_id' => $_SESSION['uzytkownik_id'],
                    ]);
                    $nowy_id = (int)$stmt->fetchColumn();
                    header('Location: /wpis/' . $nowy_id);
                    exit;
                } catch (PDOException $e) {
                    error_log('Błąd dodawania wpisu: ' . $e->getMessage());
                    $bledy[] = 'Wystąpił błąd podczas dodawania wpisu. Spróbuj ponownie.';
                }
            }
        }

        $safe_tytul = htmlspecialchars($tytul_wpisany, ENT_QUOTES, 'UTF-8');
        $safe_tresc = htmlspecialchars($tresc_wpisana, ENT_QUOTES, 'UTF-8');
        $safe_link  = htmlspecialchars($link_wpisany,  ENT_QUOTES, 'UTF-8');
        $safe_tagi  = htmlspecialchars($tagi_wpisane,  ENT_QUOTES, 'UTF-8');

        echo '<h1>Dodaj wpis</h1>';
        if (!empty($bledy)) {
            echo '<ul class="error-list">';
            foreach ($bledy as $blad) {
                echo '<li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        echo '<div class="type-toggle">';
        echo '<button type="button" class="type-btn' . ($rodzaj_wpisany === 'wpis' ? ' active' : '') . '" data-type="wpis" onclick="switchType(\'wpis\')">Wpis</button>';
        echo '<button type="button" class="type-btn' . ($rodzaj_wpisany === 'link' ? ' active' : '') . '" data-type="link" onclick="switchType(\'link\')">Link</button>';
        echo '</div>';
        echo '<form method="post" class="form-stack">';
        echo '<input type="hidden" name="rodzaj" id="rodzaj-input" value="' . htmlspecialchars($rodzaj_wpisany, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div id="sekcja-wpis"' . ($rodzaj_wpisany === 'link' ? ' style="display:none"' : '') . '>';
        echo '<textarea name="tresc" placeholder="Treść wpisu..." rows="5">' . $safe_tresc . '</textarea>';
        echo '</div>';
        echo '<div id="sekcja-link"' . ($rodzaj_wpisany === 'wpis' ? ' style="display:none"' : '') . '>';
        echo '<input type="text" name="tytul" placeholder="Tytuł" value="' . $safe_tytul . '">';
        echo '<input type="url" name="link" placeholder="URL (https://...)" value="' . $safe_link . '">';
        echo '<input type="text" name="tagi" placeholder="Tagi (np. #technologia #muzyka)" value="' . $safe_tagi . '">';
        echo '</div>';
        echo '<button type="submit" class="btn-primary">Dodaj wpis</button>';
        echo '</form>';
        echo '<script>';
        echo 'function switchType(type){';
        echo 'document.getElementById("rodzaj-input").value=type;';
        echo 'document.getElementById("sekcja-wpis").style.display=type==="wpis"?"":"none";';
        echo 'document.getElementById("sekcja-link").style.display=type==="link"?"":"none";';
        echo 'document.querySelectorAll(".type-btn").forEach(function(b){b.classList.remove("active");});';
        echo 'document.querySelector(".type-btn[data-type=\""+type+"\"]").classList.add("active");';
        echo '}';
        echo '</script>';
        break;
    case 'logowanie':
        if (isset($_SESSION['uzytkownik_id'])) {
            header('Location: /');
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
                        header('Location: /');
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
            echo '<ul class="error-list">';
            foreach ($bledy as $blad) {
                echo '<li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        echo '<div class="auth-wrap"><form method="post" class="form-stack">';
        echo '<input type="text" name="nazwa" placeholder="Nazwa użytkownika" value="' . htmlspecialchars($nazwa_wpisana, ENT_QUOTES, 'UTF-8') . '" required>';
        echo '<input type="password" name="haslo" placeholder="Hasło" required>';
        echo '<button type="submit" class="btn-primary">Zaloguj się</button>';
        echo '</form>';
        echo '<p class="auth-hint">Nie masz konta? <a href="/rejestracja">Zarejestruj się</a></p>';
        echo '</div>';
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
                    header('Location: /logowanie');
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
            echo '<ul class="error-list">';
            foreach ($bledy as $blad) {
                echo '<li>' . htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        echo '<div class="auth-wrap"><form method="post" class="form-stack">';
        echo '<input type="text" name="nazwa" placeholder="Nazwa użytkownika" value="' . htmlspecialchars($nazwa_wpisana, ENT_QUOTES, 'UTF-8') . '" required>';
        echo '<input type="password" name="haslo" placeholder="Hasło (min. 6 znaków)" required>';
        echo '<input type="password" name="haslo2" placeholder="Powtórz hasło" required>';
        echo '<button type="submit" class="btn-primary">Zarejestruj się</button>';
        echo '</form></div>';
        break;
    case 'dodaj_komentarz':
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!isset($_SESSION['uzytkownik_id'])) {
            http_response_code(403);
            echo '<ul class="error-list"><li>Musisz być zalogowany, aby dodać komentarz.</li></ul>';
            exit;
        }

        $wpis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($wpis_id <= 0) {
            http_response_code(400);
            echo '<ul class="error-list"><li>Nieprawidłowy identyfikator wpisu.</li></ul>';
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
                'SELECT k.id, k.tresc, u.nazwa AS autor, k.data_dodania,
                        COALESCE(SUM(g.wartosc), 0) AS wynik
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 LEFT JOIN glosy g ON g.komentarz_id = k.id
                 WHERE k.wpis_id = :wpis_id
                 GROUP BY k.id, k.tresc, u.nazwa, k.data_dodania
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
    case 'glosuj_komentarz':
        ob_end_clean();
        if (!isset($_SESSION['uzytkownik_id'])) {
            http_response_code(403);
            exit;
        }

        $komentarz_id = isset($_POST['komentarz_id']) ? (int)$_POST['komentarz_id'] : 0;
        $wartosc      = isset($_POST['wartosc'])      ? (int)$_POST['wartosc']      : 0;

        if ($komentarz_id <= 0 || !in_array($wartosc, [1, -1], true)) {
            http_response_code(400);
            exit;
        }

        try {
            $stmt = $polaczenie->prepare('SELECT dodaj_glos_komentarz(:uzytkownik_id, :komentarz_id, CAST(:wartosc AS SMALLINT))');
            $stmt->bindValue(':uzytkownik_id', $_SESSION['uzytkownik_id'], PDO::PARAM_INT);
            $stmt->bindValue(':komentarz_id',  $komentarz_id,              PDO::PARAM_INT);
            $stmt->bindValue(':wartosc',        $wartosc,                   PDO::PARAM_INT);
            $stmt->execute();

            $stmt2 = $polaczenie->prepare(
                'SELECT COALESCE(SUM(g.wartosc), 0)
                 FROM glosy g
                 WHERE g.komentarz_id = :komentarz_id'
            );
            $stmt2->bindValue(':komentarz_id', $komentarz_id, PDO::PARAM_INT);
            $stmt2->execute();
            $wynik_raw = $stmt2->fetchColumn();

            echo '<strong>' . (int)$wynik_raw . '</strong>';
        } catch (PDOException $e) {
            error_log('Błąd głosowania na komentarz: ' . $e->getMessage());
            http_response_code(500);
        }
        exit;
    case 'tag':
        $tag_nazwa = trim($_GET['tag'] ?? '');

        if (!preg_match('/^[\p{L}\p{N}_]+$/u', $tag_nazwa) || $tag_nazwa === '') {
            echo '<p>Nieprawidłowy tag.</p>';
            break;
        }

        $tag_wyswietlany = htmlspecialchars($tag_nazwa, ENT_QUOTES, 'UTF-8');

        try {
            $stmt = $polaczenie->prepare(
                'SELECT w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa AS autor, w.wynik, w.data_dodania,
                        COUNT(k.id) AS liczba_komentarzy
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 LEFT JOIN komentarze k ON k.wpis_id = w.id
                 WHERE w.tresc ~* :pattern
                 GROUP BY w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa, w.wynik, w.data_dodania
                 ORDER BY w.data_dodania DESC'
            );
            $stmt->execute([':pattern' => '(^|[^[:alnum:]_#])#' . $tag_nazwa . '([^[:alnum:]_]|$)']);
            $wpisy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania wpisów tagu: ' . $e->getMessage());
            $wpisy = [];
        }

        echo '<h1>Wpisy z tagiem #' . $tag_wyswietlany . '</h1>';

        if (empty($wpisy)) {
            echo '<p class="empty-state">Brak wpisów z tym tagiem.</p>';
        } else {
            echo '<ul class="card-list">';
            foreach ($wpisy as $wpis) {
                $rodzaj     = $wpis['rodzaj'] ?? 'wpis';
                $autor      = htmlspecialchars($wpis['autor'],        ENT_QUOTES, 'UTF-8');
                $wynik      = (int)$wpis['wynik'];
                $komentarze = (int)$wpis['liczba_komentarzy'];
                $data       = htmlspecialchars(
                    date('d.m.Y H:i', strtotime($wpis['data_dodania'])),
                    ENT_QUOTES, 'UTF-8'
                );
                $id = (int)$wpis['id'];
                echo '<li class="card">';
                if ($rodzaj === 'link') {
                    $tytul     = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
                    echo '<span class="type-badge type-badge--link">L</span> ';
                    echo '<a href="/wpis/' . $id . '" class="card-title">' . $tytul . '</a>';
                    if (!empty($wpis['link'])) {
                        $link_schema = strtolower(parse_url($wpis['link'], PHP_URL_SCHEME) ?: '');
                        if (in_array($link_schema, ['http', 'https'], true)) {
                            $link_host = parse_url($wpis['link'], PHP_URL_HOST) ?? '';
                            $link_url  = htmlspecialchars($wpis['link'], ENT_QUOTES, 'UTF-8');
                            if ($link_host !== '') {
                                echo ' <a href="' . $link_url . '" class="card-domain" rel="noopener noreferrer" target="_blank">(' . htmlspecialchars($link_host, ENT_QUOTES, 'UTF-8') . ')</a>';
                            }
                        }
                    }
                } else {
                    $podglad = htmlspecialchars(mb_strimwidth($wpis['tresc'] ?? '', 0, 120, '…'), ENT_QUOTES, 'UTF-8');
                    echo '<a href="/wpis/' . $id . '" class="card-title">' . $podglad . '</a>';
                }
                echo '<div class="card-meta">';
                echo 'Autor: <strong>' . $autor . '</strong>';
                echo '<span class="sep">|</span>';
                echo 'Wynik: <span id="wynik-wpisu-' . $id . '" class="score">' . $wynik . '</span>';
                if (isset($_SESSION['uzytkownik_id'])) {
                    echo ' <button type="button" class="btn-vote up" hx-post="/glosuj" hx-target="#wynik-wpisu-' . $id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $id . '","wartosc":"1"}\' aria-label="Zagłosuj za">+</button>';
                    echo ' <button type="button" class="btn-vote down" hx-post="/glosuj" hx-target="#wynik-wpisu-' . $id . '" hx-swap="innerHTML" hx-vals=\'{"wpis_id":"' . $id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw">−</button>';
                }
                echo '<span class="sep">|</span>';
                echo 'Komentarze: <strong>' . $komentarze . '</strong>';
                echo '<span class="sep">|</span>';
                echo $data;
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }
        break;
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
        header('Location: /logowanie');
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

        body {
            font-family: Verdana, Geneva, sans-serif;
            background: #f6f6f6;
            color: #000;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-size: 13px;
            line-height: 1.5;
        }

        /* ── Header ── */
        header {
            background: #000;
            color: #fff;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        header .nazwa-serwisu {
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            color: #fff;
            border: 1px solid #fff;
            padding: 1px 4px;
        }

        nav { display: flex; align-items: center; gap: 0.1rem; flex: 1; }

        nav a {
            color: #fff;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 2px 6px;
        }

        nav a:hover { text-decoration: underline; }

        .nav-sep { color: #888; padding: 0 2px; }

        .nav-github { margin-left: auto; color: #fff; text-decoration: none; font-size: 0.85rem; padding: 2px 6px; }
        .nav-github:hover { text-decoration: underline; }

        .nav-user {
            color: #ccc;
            font-size: 0.85rem;
            padding: 2px 4px;
        }

        /* ── Layout ── */
        main {
            flex: 1;
            max-width: 860px;
            width: 100%;
            margin: 1rem auto;
            padding: 0 8px;
        }

        /* ── Typography ── */
        h1 {
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        h2 {
            font-size: 0.95rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        a { color: #000; }
        a:visited { color: #444; }

        /* ── Post List ── */
        .card-list {
            list-style: none;
            border-top: 1px solid #ccc;
        }

        .card {
            border-bottom: 1px solid #e8e8e8;
            padding: 6px 4px;
        }

        .card-title {
            font-size: 0.95rem;
            text-decoration: none;
            color: #000;
        }

        .card-title:hover { text-decoration: underline; }
        .card-title:visited { color: #444; }

        .card-meta {
            margin-top: 2px;
            font-size: 0.78rem;
            color: #555;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0 0.3rem;
        }

        .card-meta strong { color: #000; font-weight: normal; }
        .card-meta .sep { color: #ccc; }

        /* ── Score & Vote buttons ── */
        .score { font-weight: bold; }

        .btn-vote {
            cursor: pointer;
            font-size: 0.78rem;
            font-family: Verdana, Geneva, sans-serif;
            border: none;
            background: none;
            color: #555;
            padding: 0 2px;
        }

        .btn-vote:hover { color: #000; text-decoration: underline; }

        /* ── Article (single post) ── */
        .article-card {
            border: 1px solid #ddd;
            background: #fff;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .article-card h1 { margin-bottom: 0.75rem; }

        .article-body {
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }

        .article-link {
            display: inline-block;
            margin-bottom: 0.75rem;
            color: #000;
            font-size: 0.85rem;
            word-break: break-all;
            text-decoration: underline;
        }

        /* ── Comments ── */
        .comments-section { margin-top: 1.5rem; }

        .comment-list {
            list-style: none;
            border-top: 1px solid #ccc;
            margin-top: 0.5rem;
        }

        .comment-item {
            border-bottom: 1px solid #e8e8e8;
            padding: 8px 4px;
        }

        .comment-meta {
            font-size: 0.78rem;
            color: #555;
            margin-bottom: 4px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0 0.3rem;
        }

        .comment-meta strong { color: #000; font-weight: bold; }

        /* ── Forms ── */
        .form-stack {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-width: 500px;
        }

        .form-stack input,
        .form-stack textarea {
            font-family: Verdana, Geneva, sans-serif;
            font-size: 0.9rem;
            padding: 4px 6px;
            border: 1px solid #ccc;
            background: #fff;
            color: #000;
            width: 100%;
        }

        .form-stack input:focus,
        .form-stack textarea:focus {
            outline: 2px solid #000;
        }

        .btn-primary {
            font-family: Verdana, Geneva, sans-serif;
            font-size: 0.85rem;
            padding: 4px 12px;
            background: #fff;
            color: #000;
            border: 1px solid #000;
            cursor: pointer;
            align-self: flex-start;
        }

        .btn-primary:hover { background: #000; color: #fff; }

        .form-stack textarea { resize: vertical; }
        .form-stack--comment { margin-top: 1rem; }

        /* ── Empty state ── */
        .empty-state { margin-top: 0.5rem; color: #555; }

        .error-list {
            list-style: none;
            margin-bottom: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .error-list li {
            border: 1px solid #999;
            padding: 4px 8px;
            font-size: 0.85rem;
        }

        /* ── Pagination ── */
        .pagination {
            margin-top: 1rem;
            display: flex;
            gap: 0.2rem;
            align-items: center;
        }

        .pagination a {
            font-size: 0.85rem;
            padding: 2px 6px;
            border: 1px solid #ccc;
            text-decoration: none;
            color: #000;
        }

        .pagination a:hover,
        .pagination a.active {
            background: #000;
            border-color: #000;
            color: #fff;
        }

        /* ── Footer ── */
        footer {
            background: #000;
            color: #888;
            text-align: center;
            padding: 6px;
            font-size: 0.78rem;
            margin-top: 2rem;
        }

        /* ── Auth pages ── */
        .auth-wrap { max-width: 360px; }
        .auth-hint { margin-top: 0.75rem; font-size: 0.85rem; color: #555; }
        .auth-hint a { color: #000; text-decoration: underline; }

        /* ── Tags ── */
        .tag-link { color: #000; text-decoration: none; font-weight: bold; }
        .tag-link:hover { text-decoration: underline; }
        .tag-link:visited { color: #444; }

        /* ── Type toggle (add post page) ── */
        .type-toggle {
            display: flex;
            gap: 0;
            margin-bottom: 0.75rem;
            border: 1px solid #000;
            width: fit-content;
        }

        .type-btn {
            font-family: Verdana, Geneva, sans-serif;
            font-size: 0.85rem;
            padding: 4px 16px;
            background: #fff;
            color: #000;
            border: none;
            cursor: pointer;
        }

        .type-btn + .type-btn { border-left: 1px solid #000; }
        .type-btn.active { background: #000; color: #fff; }
        .type-btn:hover:not(.active) { background: #f0f0f0; }

        /* ── Type badge (post list) ── */
        .type-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 0 3px;
            border: 1px solid;
            vertical-align: middle;
        }

        .type-badge--link { border-color: #555; color: #555; }

        .card-domain {
            font-size: 0.78rem;
            color: #555;
            text-decoration: none;
        }

        .card-domain:hover { text-decoration: underline; }

        .article-tags { margin-top: 0.5rem; }
    </style>
</head>
<body>
    <header>
        <a class="nazwa-serwisu" href="/">Mirkovibe</a>
        <nav>
            <a href="/">Główna</a>
            <span class="nav-sep">|</span>
            <a href="/dodaj">Dodaj wpis</a>
            <?php if (isset($_SESSION['uzytkownik_id'])): ?>
                <span class="nav-sep">|</span>
                <span class="nav-user">Witaj, <?= htmlspecialchars($_SESSION['uzytkownik_nazwa'], ENT_QUOTES, 'UTF-8') ?>!</span>
                <span class="nav-sep">|</span>
                <a href="/wyloguj">Wyloguj</a>
            <?php else: ?>
                <span class="nav-sep">|</span>
                <a href="/logowanie">Logowanie</a>
                <span class="nav-sep">|</span>
                <a href="/rejestracja">Rejestracja</a>
            <?php endif; ?>
            <a class="nav-github" href="https://github.com/KalwiRemi/mirkovibe" target="_blank" rel="noopener noreferrer">GitHub</a>
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
