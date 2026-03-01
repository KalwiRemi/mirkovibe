<?php

session_start();

define('BAZA_HOST',            getenv('BAZA_HOST')            ?: 'localhost');
define('BAZA_NAZWA',           getenv('BAZA_NAZWA')           ?: 'mirkovibe');
define('BAZA_UZYTKOWNIK',      getenv('BAZA_UZYTKOWNIK')      ?: 'postgres');
define('BAZA_HASLO',           getenv('BAZA_HASLO')           ?: '');
define('APP_URL', rtrim(getenv('APP_URL') ?:
    ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    '/'));
define('NAZWA_ADMINISTRATORA', getenv('NAZWA_ADMINISTRATORA') ?: '');
define('DOMYSLNY_CZAS_WPISU',       12);
define('DOMYSLNY_CZAS_KOMENTARZA',   1);

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

$dozwolone_strony = ['glowna', 'wpis', 'dodaj', 'logowanie', 'rejestracja', 'wyloguj', 'dodaj_komentarz', 'glosuj', 'glosuj_komentarz', 'tag', 'admin', 'weryfikuj_email', 'komentarz', 'moderuj_wpis', 'moderuj_komentarz'];

function formatujCzasOczekiwania(float $godziny): string {
    if ($godziny < 1) {
        $minuty = (int)ceil($godziny * 60);
        if ($minuty === 1) return '1 minutę';
        if ($minuty <= 4) return $minuty . ' minuty';
        return $minuty . ' minut';
    }
    $h = (int)ceil($godziny);
    if ($h === 1) return '1 godzinę';
    if ($h <= 4) return $h . ' godziny';
    return $h . ' godzin';
}

function sprawdzKarencje(PDO $polaczenie, int $uzytkownik_id, string $typ): float {
    $klucz    = $typ === 'wpis' ? 'minimalny_czas_wpisu' : 'minimalny_czas_komentarza';
    $domyslna = $typ === 'wpis' ? (string)DOMYSLNY_CZAS_WPISU : (string)DOMYSLNY_CZAS_KOMENTARZA;
    try {
        $stmt_cfg = $polaczenie->prepare('SELECT wartosc FROM konfiguracja WHERE klucz = :klucz');
        $stmt_cfg->execute([':klucz' => $klucz]);
        $minimalny_czas = (int)($stmt_cfg->fetchColumn() ?: $domyslna);
        $stmt_usr = $polaczenie->prepare('SELECT data_rejestracji FROM uzytkownicy WHERE id = :id');
        $stmt_usr->execute([':id' => $uzytkownik_id]);
        $data_rejestracji = $stmt_usr->fetchColumn();
        $godziny_od_rejestracji = (time() - strtotime($data_rejestracji)) / 3600;
        return max(0.0, $minimalny_czas - $godziny_od_rejestracji);
    } catch (PDOException $e) {
        error_log('Błąd sprawdzania karencji: ' . $e->getMessage());
        return 0.0;
    }
}

function pobierzGlosyWpisow(PDO $polaczenie, int $uzytkownik_id, array $wpisy): array {
    if (empty($wpisy)) return [];
    $ids = array_column($wpisy, 'id');
    $miejsca = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $polaczenie->prepare("SELECT wpis_id, wartosc FROM glosy WHERE uzytkownik_id = ? AND wpis_id IN ($miejsca)");
        $stmt->execute(array_merge([$uzytkownik_id], $ids));
        $wynik = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $wynik[(int)$g['wpis_id']] = (int)$g['wartosc'];
        }
        return $wynik;
    } catch (PDOException $e) {
        error_log('Błąd pobierania głosów wpisów: ' . $e->getMessage());
        return [];
    }
}

function pobierzGlosyKomentarzy(PDO $polaczenie, int $uzytkownik_id, array $komentarze): array {
    if (empty($komentarze)) return [];
    $ids = array_column($komentarze, 'id');
    $miejsca = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $polaczenie->prepare("SELECT komentarz_id, wartosc FROM glosy WHERE uzytkownik_id = ? AND komentarz_id IN ($miejsca)");
        $stmt->execute(array_merge([$uzytkownik_id], $ids));
        $wynik = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $wynik[(int)$g['komentarz_id']] = (int)$g['wartosc'];
        }
        return $wynik;
    } catch (PDOException $e) {
        error_log('Błąd pobierania głosów komentarzy: ' . $e->getMessage());
        return [];
    }
}

function renderujSekcjeGlosow(string $typ, int $id, int $wynik, bool $zalogowany, int $glos_uzytkownika = 0, bool $jest_autorem = false): string {
    $trasa     = $typ === 'wpis' ? '/glosuj' : '/glosuj_komentarz';
    $pole_id   = $typ === 'wpis' ? 'wpis_id' : 'komentarz_id';
    $id_sekcji = 'glosy-' . $typ . '-' . $id;
    $id_wyniku = 'wynik-' . $typ . '-' . $id;
    $html  = '<div id="' . $id_sekcji . '" class="card-votes">';
    if ($zalogowany && !$jest_autorem) {
        $klasa_up = 'btn-vote up' . ($glos_uzytkownika === 1 ? ' active' : '');
        $html .= '<button type="button" class="' . $klasa_up . '" hx-post="' . $trasa . '" hx-target="#' . $id_sekcji . '" hx-swap="outerHTML" hx-vals=\'{"' . $pole_id . '":"' . $id . '","wartosc":"1"}\' aria-label="Zagłosuj za">▲</button>';
    } else {
        $html .= '<span class="vote-icon">▲</span>';
    }
    $html .= '<span id="' . $id_wyniku . '" class="score">' . $wynik . '</span>';
    if ($zalogowany && !$jest_autorem) {
        $klasa_down = 'btn-vote down' . ($glos_uzytkownika === -1 ? ' active' : '');
        $html .= '<button type="button" class="' . $klasa_down . '" hx-post="' . $trasa . '" hx-target="#' . $id_sekcji . '" hx-swap="outerHTML" hx-vals=\'{"' . $pole_id . '":"' . $id . '","wartosc":"-1"}\' aria-label="Zagłosuj przeciw">▼</button>';
    } else {
        $html .= '<span class="vote-icon">▼</span>';
    }
    $html .= '</div>';
    return $html;
}

function renderujElementKomentarza(array $komentarz, bool $zalogowany, array $glosy, array $dzieci, int $glebokosc = 0, bool $jest_moderatorem = false, int $uzytkownik_id = 0): string {
    $k_id           = (int)$komentarz['id'];
    $k_autor        = htmlspecialchars($komentarz['autor'], ENT_QUOTES, 'UTF-8');
    $k_tresc        = htmlspecialchars($komentarz['tresc'], ENT_QUOTES, 'UTF-8');
    $k_data         = htmlspecialchars(date('d.m.Y H:i', strtotime($komentarz['data_dodania'])), ENT_QUOTES, 'UTF-8');
    $k_wynik        = (int)($komentarz['wynik'] ?? 0);
    $k_glos         = (int)($glosy[$k_id] ?? 0);
    $usunieto       = !empty($komentarz['usunieto']);
    $jest_autorem_k = $uzytkownik_id > 0 && $uzytkownik_id === (int)($komentarz['autor_id'] ?? 0);

    $html  = '<li class="comment-item">';
    $html .= '<div class="card-layout">';
    $html .= renderujSekcjeGlosow('komentarz', $k_id, $k_wynik, $zalogowany, $k_glos, $jest_autorem_k);
    $html .= '<div class="card-content">';
    $html .= '<div class="card-header">';
    $html .= '<strong class="card-author">' . $k_autor . '</strong>';
    $html .= '<span class="card-date">' . $k_data . '</span>';
    if ($jest_moderatorem) {
        if ($usunieto) {
            $html .= '<form method="post" action="/moderuj_komentarz" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="komentarz_id" value="' . $k_id . '">'
                   . '<input type="hidden" name="akcja" value="przywroc">'
                   . '<button type="submit" class="btn-mod btn-mod--przywroc">MODERACJA: PRZYWRÓĆ</button>'
                   . '</form>';
        } else {
            $html .= '<form method="post" action="/moderuj_komentarz" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="komentarz_id" value="' . $k_id . '">'
                   . '<input type="hidden" name="akcja" value="usun">'
                   . '<button type="submit" class="btn-mod btn-mod--usun">MODERACJA: USUŃ</button>'
                   . '</form>';
        }
    }
    $html .= '</div>';
    if ($usunieto && !$jest_moderatorem) {
        $html .= '<div class="card-body"><em class="wpis-usuniety-info">Ten komentarz został usunięty przez moderatora</em></div>';
    } else {
        $tresc_html = nl2br(parsujTagi($k_tresc));
        $html .= '<div class="card-body' . ($usunieto ? ' wpis-usuniety' : '') . '">' . $tresc_html . '</div>';
    }
    $html .= '<div class="card-footer">';
    $html .= '<a href="/komentarz/' . $k_id . '" class="card-meta-link card-meta-link--reply">odpowiedz</a>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    if (!empty($dzieci[$k_id]) && $glebokosc < 10) {
        $html .= '<ul class="comment-list comment-list--zagniezdzone">';
        foreach ($dzieci[$k_id] as $dziecko) {
            $html .= renderujElementKomentarza($dziecko, $zalogowany, $glosy, $dzieci, $glebokosc + 1, $jest_moderatorem, $uzytkownik_id);
        }
        $html .= '</ul>';
    }

    $html .= '</li>';
    return $html;
}

function renderujKomentarze(array $komentarze, int $wpis_id, bool $zalogowany, string $blad = '', float $godziny_oczekiwania = 0.0, array $glosy_uzytkownika = [], bool $jest_moderatorem = false, int $uzytkownik_id = 0): string {
    $html  = '<div id="komentarze-sekcja">';
    $html .= '<section class="comments-section">';

    if (!empty($komentarze)) {
        $dzieci   = [];
        $korzenie = [];
        foreach ($komentarze as $k) {
            $rodzic = ($k['rodzic_id'] !== null) ? (int)$k['rodzic_id'] : null;
            if ($rodzic !== null) {
                $dzieci[$rodzic][] = $k;
            } else {
                $korzenie[] = $k;
            }
        }
        $html .= '<ul class="comment-list">';
        foreach ($korzenie as $k) {
            $html .= renderujElementKomentarza($k, $zalogowany, $glosy_uzytkownika, $dzieci, 0, $jest_moderatorem, $uzytkownik_id);
        }
        $html .= '</ul>';
    }
    $html .= '</section>';

    if ($zalogowany) {
        if ($godziny_oczekiwania > 0) {
            $html .= '<p class="empty-state">Możesz dodać komentarz za ' . formatujCzasOczekiwania($godziny_oczekiwania) . '.</p>';
        } else {
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
    }

    $html .= '</div>';
    return $html;
}
function renderujKarteWpisu(array $wpis, bool $zalogowany, int $glos_uzytkownika = 0, bool $jest_moderatorem = false, int $uzytkownik_id = 0): string {
    $rodzaj          = $wpis['rodzaj'] ?? 'wpis';
    $autor           = htmlspecialchars($wpis['autor'], ENT_QUOTES, 'UTF-8');
    $wynik           = (int)$wpis['wynik'];
    $komentarze      = (int)($wpis['liczba_komentarzy'] ?? 0);
    $data            = htmlspecialchars(date('d.m.Y H:i', strtotime($wpis['data_dodania'])), ENT_QUOTES, 'UTF-8');
    $id              = (int)$wpis['id'];
    $usunieto        = !empty($wpis['usunieto']);
    $jest_autorem_w  = $uzytkownik_id > 0 && $uzytkownik_id === (int)($wpis['autor_id'] ?? 0);

    $html  = '<li class="card">';
    $html .= '<div class="card-layout">';

    $html .= renderujSekcjeGlosow('wpis', $id, $wynik, $zalogowany, $glos_uzytkownika, $jest_autorem_w);

    $html .= '<div class="card-content">';
    $html .= '<div class="card-header">';
    $html .= '<strong class="card-author">' . $autor . '</strong>';
    $html .= '<span class="card-date">' . $data . '</span>';
    if ($jest_moderatorem) {
        if ($usunieto) {
            $html .= '<form method="post" action="/moderuj_wpis" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="wpis_id" value="' . $id . '">'
                   . '<input type="hidden" name="akcja" value="przywroc">'
                   . '<button type="submit" class="btn-mod btn-mod--przywroc">MODERACJA: PRZYWRÓĆ</button>'
                   . '</form>';
        } else {
            $html .= '<form method="post" action="/moderuj_wpis" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="wpis_id" value="' . $id . '">'
                   . '<input type="hidden" name="akcja" value="usun">'
                   . '<button type="submit" class="btn-mod btn-mod--usun">MODERACJA: USUŃ</button>'
                   . '</form>';
        }
    }
    $html .= '</div>';

    $html .= '<div class="card-body">';
    if ($usunieto && !$jest_moderatorem) {
        $html .= '<em class="wpis-usuniety-info">Ten wpis został usunięty przez moderatora</em>';
    } elseif ($rodzaj === 'link') {
        $tytul = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
        $kl_usuniety = $usunieto ? ' wpis-usuniety' : '';
        $html .= '<span class="type-badge type-badge--link">L</span> ';
        $html .= '<a href="/wpis/' . $id . '" class="card-title' . $kl_usuniety . '">' . $tytul . '</a>';
        if (!empty($wpis['link'])) {
            $link_schema = strtolower(parse_url($wpis['link'], PHP_URL_SCHEME) ?: '');
            if (in_array($link_schema, ['http', 'https'], true)) {
                $link_host = parse_url($wpis['link'], PHP_URL_HOST) ?? '';
                $link_url  = htmlspecialchars($wpis['link'], ENT_QUOTES, 'UTF-8');
                if ($link_host !== '') {
                    $html .= ' <a href="' . $link_url . '" class="card-domain" rel="noopener noreferrer" target="_blank">(' . htmlspecialchars($link_host, ENT_QUOTES, 'UTF-8') . ')</a>';
                }
            }
        }
    } else {
        $podglad = htmlspecialchars(mb_strimwidth($wpis['tresc'] ?? '', 0, 120, '…'), ENT_QUOTES, 'UTF-8');
        $kl_usuniety = $usunieto ? ' wpis-usuniety' : '';
        $html .= '<a href="/wpis/' . $id . '" class="card-title' . $kl_usuniety . '">' . $podglad . '</a>';
    }
    $html .= '</div>';

    $html .= '<div class="card-footer">';
    $html .= '<a href="/wpis/' . $id . '" class="card-meta-link">komentarze (' . $komentarze . ')</a>';
    $html .= '<a href="/wpis/' . $id . '" class="card-meta-link card-meta-link--reply">odpowiedz</a>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';
    $html .= '</li>';

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
        case 'komentarz':
            $_GET['strona'] = 'komentarz';
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
        case 'moderuj_komentarz':
            $_GET['strona'] = 'moderuj_komentarz';
            break;
        case 'tag':
            $_GET['strona'] = 'tag';
            if ($seg1 !== null && preg_match('/^[\p{L}\p{N}_]+$/u', $seg1)) {
                $_GET['tag'] = $seg1;
            }
            break;
        case 'admin':
            $_GET['strona'] = 'admin';
            break;
        case 'moderuj_wpis':
            $_GET['strona'] = 'moderuj_wpis';
            break;
        case 'weryfikuj_email':
            $_GET['strona'] = 'weryfikuj_email';
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
                'SELECT w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa AS autor, w.autor_id, w.wynik, w.data_dodania, w.usunieto,
                        COUNT(k.id) AS liczba_komentarzy
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 LEFT JOIN komentarze k ON k.wpis_id = w.id
                 GROUP BY w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa, w.autor_id, w.wynik, w.data_dodania, w.usunieto
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

        $glosy_uzytkownika_wpisy = isset($_SESSION['uzytkownik_id'])
            ? pobierzGlosyWpisow($polaczenie, (int)$_SESSION['uzytkownik_id'], $wpisy)
            : [];

        echo '<h1>Lista wpisów</h1>';

        if (empty($wpisy)) {
            echo '<p>Brak wpisów.</p>';
        } else {
            echo '<ul class="card-list">';
            foreach ($wpisy as $wpis) {
                echo renderujKarteWpisu($wpis, isset($_SESSION['uzytkownik_id']), $glosy_uzytkownika_wpisy[(int)$wpis['id']] ?? 0, !empty($_SESSION['jest_adminem']) || !empty($_SESSION['jest_moderatorem']), (int)($_SESSION['uzytkownik_id'] ?? 0));
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
                'SELECT w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa AS autor, w.autor_id, w.wynik, w.data_dodania, w.usunieto,
                        COUNT(k.id) AS liczba_komentarzy
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 LEFT JOIN komentarze k ON k.wpis_id = w.id
                 WHERE w.id = :id
                 GROUP BY w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa, w.autor_id, w.wynik, w.data_dodania, w.usunieto'
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

        $rodzaj_wpisu      = $wpis['rodzaj'] ?? 'wpis';
        $autor             = htmlspecialchars($wpis['autor'], ENT_QUOTES, 'UTF-8');
        $wynik             = (int)$wpis['wynik'];
        $data              = htmlspecialchars(date('d.m.Y H:i', strtotime($wpis['data_dodania'])), ENT_QUOTES, 'UTF-8');
        $liczba_komentarzy = (int)($wpis['liczba_komentarzy'] ?? 0);
        $zalogowany_wpis   = isset($_SESSION['uzytkownik_id']);
        $usunieto_wpis     = !empty($wpis['usunieto']);
        $jest_mod_lub_adm  = !empty($_SESSION['jest_adminem']) || !empty($_SESSION['jest_moderatorem']);
        $jest_autorem_wpis = $zalogowany_wpis && (int)($_SESSION['uzytkownik_id'] ?? 0) === (int)($wpis['autor_id'] ?? 0);

        $glos_wpisu = 0;
        if ($zalogowany_wpis) {
            try {
                $stmt_gw = $polaczenie->prepare('SELECT wartosc FROM glosy WHERE uzytkownik_id = :u AND wpis_id = :w');
                $stmt_gw->execute([':u' => $_SESSION['uzytkownik_id'], ':w' => $wpis_id]);
                $glos_wpisu = (int)($stmt_gw->fetchColumn() ?: 0);
            } catch (PDOException $e) {
                error_log('Błąd pobierania głosu użytkownika: ' . $e->getMessage());
            }
        }

        echo '<div class="card card-layout card-layout--detail">';

        echo renderujSekcjeGlosow('wpis', $wpis_id, $wynik, $zalogowany_wpis, $glos_wpisu, $jest_autorem_wpis);

        echo '<div class="card-content">';
        echo '<div class="card-header">';
        echo '<strong class="card-author">' . $autor . '</strong>';
        echo '<span class="card-date">' . $data . '</span>';
        if ($jest_mod_lub_adm) {
            if ($usunieto_wpis) {
                echo '<form method="post" action="/moderuj_wpis" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="wpis_id" value="' . $wpis_id . '">'
                   . '<input type="hidden" name="akcja" value="przywroc">'
                   . '<button type="submit" class="btn-mod btn-mod--przywroc">MODERACJA: PRZYWRÓĆ</button>'
                   . '</form>';
            } else {
                echo '<form method="post" action="/moderuj_wpis" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="wpis_id" value="' . $wpis_id . '">'
                   . '<input type="hidden" name="akcja" value="usun">'
                   . '<button type="submit" class="btn-mod btn-mod--usun">MODERACJA: USUŃ</button>'
                   . '</form>';
            }
        }
        echo '</div>';

        echo '<div class="card-body article-body">';
        if ($usunieto_wpis && !$jest_mod_lub_adm) {
            echo '<em class="wpis-usuniety-info">Ten wpis został usunięty przez moderatora</em>';
        } else {
            $otwieracz = $usunieto_wpis ? '<div class="wpis-usuniety">' : '';
            $zamykacz  = $usunieto_wpis ? '</div>' : '';
            echo $otwieracz;
            if ($rodzaj_wpisu === 'link') {
                $tytul = htmlspecialchars($wpis['tytul'] ?? '(bez tytułu)', ENT_QUOTES, 'UTF-8');
                echo '<p><span class="type-badge type-badge--link">L</span> ' . $tytul . '</p>';
                if (!empty($wpis['link'])) {
                    $link_raw    = $wpis['link'];
                    $link_schema = strtolower(parse_url($link_raw, PHP_URL_SCHEME) ?: '');
                    if (in_array($link_schema, ['http', 'https'], true)) {
                        $link = htmlspecialchars($link_raw, ENT_QUOTES, 'UTF-8');
                        echo '<a href="' . $link . '" class="article-link" rel="noopener noreferrer" target="_blank">' . $link . '</a>';
                    }
                }
                if (!empty($wpis['tresc'])) {
                    echo '<div class="article-tags">' . parsujTagi(htmlspecialchars($wpis['tresc'], ENT_QUOTES, 'UTF-8')) . '</div>';
                }
            } else {
                if (!empty($wpis['tresc'])) {
                    echo nl2br(parsujTagi(htmlspecialchars($wpis['tresc'], ENT_QUOTES, 'UTF-8')));
                }
            }
            echo $zamykacz;
        }
        echo '</div>';

        echo '<div class="card-footer">';
        echo '<a href="#komentarze-sekcja" class="card-meta-link">komentarze (' . $liczba_komentarzy . ')</a>';
        echo '<a href="#komentarze-sekcja" class="card-meta-link card-meta-link--reply">odpowiedz</a>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        try {
            $stmt = $polaczenie->prepare(
                'SELECT k.id, k.tresc, k.rodzic_id, k.usunieto, u.nazwa AS autor, k.autor_id, k.data_dodania,
                        COALESCE(SUM(g.wartosc), 0) AS wynik
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 LEFT JOIN glosy g ON g.komentarz_id = k.id
                 WHERE k.wpis_id = :wpis_id
                 GROUP BY k.id, k.tresc, k.rodzic_id, k.usunieto, u.nazwa, k.autor_id, k.data_dodania
                 ORDER BY k.data_dodania ASC'
            );
            $stmt->execute([':wpis_id' => $wpis_id]);
            $komentarze = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania komentarzy: ' . $e->getMessage());
            $komentarze = [];
        }

        $glosy_komentarzy = isset($_SESSION['uzytkownik_id'])
            ? pobierzGlosyKomentarzy($polaczenie, (int)$_SESSION['uzytkownik_id'], $komentarze)
            : [];

        echo renderujKomentarze($komentarze, $wpis_id, isset($_SESSION['uzytkownik_id']),
            '',
            (isset($_SESSION['uzytkownik_id']) && empty($_SESSION['jest_adminem']))
                ? sprawdzKarencje($polaczenie, (int)$_SESSION['uzytkownik_id'], 'komentarz')
                : 0.0,
            $glosy_komentarzy,
            !empty($_SESSION['jest_adminem']) || !empty($_SESSION['jest_moderatorem']),
            (int)($_SESSION['uzytkownik_id'] ?? 0)
        );
        break;
    case 'dodaj':
        if (!isset($_SESSION['uzytkownik_id'])) {
            header('Location: /logowanie');
            exit;
        }

        if (empty($_SESSION['jest_adminem'])) {
            $godziny_oczekiwania_wpis = sprawdzKarencje($polaczenie, (int)$_SESSION['uzytkownik_id'], 'wpis');
            if ($godziny_oczekiwania_wpis > 0) {
                echo '<h1>Dodaj wpis</h1>';
                echo '<p class="empty-state">Możesz dodać wpis za ' . formatujCzasOczekiwania($godziny_oczekiwania_wpis) . '.</p>';
                break;
            }
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
                    $stmt = $polaczenie->prepare('SELECT id, nazwa, haslo_hash, jest_adminem, jest_moderatorem, email, email_zweryfikowany FROM uzytkownicy WHERE nazwa = :nazwa');
                    $stmt->execute([':nazwa' => $nazwa_wpisana]);
                    $uzytkownik = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($uzytkownik && password_verify($haslo, $uzytkownik['haslo_hash'])) {
                        if ($uzytkownik['email'] !== null && !$uzytkownik['email_zweryfikowany']) {
                            $bledy[] = 'Twój adres email nie został jeszcze zweryfikowany. Sprawdź swoją skrzynkę pocztową.';
                        } else {
                            session_regenerate_id(true);
                            $_SESSION['uzytkownik_id']       = $uzytkownik['id'];
                            $_SESSION['uzytkownik_nazwa']    = $uzytkownik['nazwa'];
                            $_SESSION['jest_adminem']        = (bool)$uzytkownik['jest_adminem'];
                            $_SESSION['jest_moderatorem']    = (bool)$uzytkownik['jest_moderatorem'];
                            header('Location: /');
                            exit;
                        }
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
        $email_wpisany = '';

        $rejestracja_wlaczona = false;
        try {
            $stmt_cfg = $polaczenie->prepare("SELECT wartosc FROM konfiguracja WHERE klucz = 'rejestracja_wlaczona'");
            $stmt_cfg->execute();
            $cfg_wartosc = $stmt_cfg->fetchColumn();
            $rejestracja_wlaczona = ($cfg_wartosc === 'true');
        } catch (PDOException $e) {
            error_log('Błąd odczytu konfiguracji: ' . $e->getMessage());
        }

        if (!$rejestracja_wlaczona) {
            $nazwa_wpisana = trim($_POST['nazwa'] ?? '');
            $jest_nazwa_admina = NAZWA_ADMINISTRATORA !== '' && $nazwa_wpisana === NAZWA_ADMINISTRATORA;

            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$jest_nazwa_admina) {
                echo '<h1>Rejestracja</h1>';
                echo '<p>Rejestracja wyłączona.</p>';
                break;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nazwa_wpisana = trim($_POST['nazwa'] ?? '');
            $haslo         = $_POST['haslo']  ?? '';
            $haslo2        = $_POST['haslo2'] ?? '';
            $email_wpisany = trim($_POST['email'] ?? '');

            if (strlen($nazwa_wpisana) < 3) {
                $bledy[] = 'Nazwa użytkownika musi mieć co najmniej 3 znaki.';
            }
            if (strlen($haslo) < 6) {
                $bledy[] = 'Hasło musi mieć co najmniej 6 znaków.';
            }
            if ($haslo !== $haslo2) {
                $bledy[] = 'Hasła nie są zgodne.';
            }
            if (!filter_var($email_wpisany, FILTER_VALIDATE_EMAIL)) {
                $bledy[] = 'Podaj prawidłowy adres email.';
            }

            if (empty($bledy)) {
                try {
                    $polaczenie->beginTransaction();

                    $stmt = $polaczenie->prepare('SELECT zarejestruj_uzytkownika(:nazwa, :haslo)');
                    $stmt->execute([':nazwa' => $nazwa_wpisana, ':haslo' => $haslo]);

                    $jest_adminem = NAZWA_ADMINISTRATORA !== '' && $nazwa_wpisana === NAZWA_ADMINISTRATORA;
                    if ($jest_adminem) {
                        $stmt_admin = $polaczenie->prepare('UPDATE uzytkownicy SET jest_adminem = TRUE, email = :email, email_zweryfikowany = TRUE WHERE nazwa = :nazwa');
                        $stmt_admin->execute([':email' => $email_wpisany, ':nazwa' => $nazwa_wpisana]);
                    } else {
                        $stmt_email = $polaczenie->prepare('UPDATE uzytkownicy SET email = :email, email_zweryfikowany = FALSE WHERE nazwa = :nazwa');
                        $stmt_email->execute([':email' => $email_wpisany, ':nazwa' => $nazwa_wpisana]);

                        $token = bin2hex(random_bytes(32));
                        $stmt_token = $polaczenie->prepare(
                            'INSERT INTO tokeny_weryfikacji (token, uzytkownik_id, data_wygasniecia)
                             SELECT :token, id, NOW() + INTERVAL \'24 hours\' FROM uzytkownicy WHERE nazwa = :nazwa'
                        );
                        $stmt_token->execute([':token' => $token, ':nazwa' => $nazwa_wpisana]);
                    }

                    $polaczenie->commit();

                    if (!$jest_adminem) {
                        $link_weryfikacji = APP_URL . '/weryfikuj_email?token=' . urlencode($token);
                        $temat = 'Weryfikacja adresu email - Mirkovibe';
                        $tresc_email = "Witaj, " . $nazwa_wpisana . "!\n\n"
                            . "Kliknij poniższy link, aby zweryfikować swój adres email:\n\n"
                            . $link_weryfikacji . "\n\n"
                            . "Link jest ważny przez 24 godziny.\n\n"
                            . "Jeśli nie zakładałeś/aś konta w serwisie Mirkovibe, zignoruj tę wiadomość.";
                        $naglowki = 'From: ' . (getenv('SMTP_FROM') ?: 'noreply@' . (parse_url(APP_URL, PHP_URL_HOST) ?? 'mirkovibe')) . "\r\n"
                            . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
                        $wyslano = mail($email_wpisany, $temat, $tresc_email, $naglowki);
                        if (!$wyslano) {
                            error_log('Nie udało się wysłać emaila weryfikacyjnego do: ' . $email_wpisany);
                        }
                        echo '<h1>Rejestracja</h1>';
                        echo '<p>Konto zostało utworzone. Na adres <strong>' . htmlspecialchars($email_wpisany, ENT_QUOTES, 'UTF-8') . '</strong> wysłaliśmy link weryfikacyjny. Sprawdź swoją skrzynkę pocztową (w tym folder spam) i kliknij link, aby aktywować konto.</p>';
                        break;
                    }

                    header('Location: /logowanie');
                    exit;
                } catch (PDOException $e) {
                    if ($polaczenie->inTransaction()) {
                        $polaczenie->rollBack();
                    }
                    if ($e->getCode() === '23505' || $e->getCode() === 'P0001') {
                        $bledy[] = 'Użytkownik o podanej nazwie lub adresie email już istnieje.';
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
        echo '<input type="email" name="email" placeholder="Adres email" value="' . htmlspecialchars($email_wpisany, ENT_QUOTES, 'UTF-8') . '" required>';
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

        $rodzic_id_raw = (int)($_POST['rodzic_id'] ?? 0);
        $rodzic_id = $rodzic_id_raw > 0 ? $rodzic_id_raw : null;

        if ($rodzic_id !== null) {
            try {
                $stmt_rch = $polaczenie->prepare('SELECT wpis_id FROM komentarze WHERE id = :id');
                $stmt_rch->execute([':id' => $rodzic_id]);
                $rodzic_wpis = $stmt_rch->fetchColumn();
                if ($rodzic_wpis === false || (int)$rodzic_wpis !== $wpis_id) {
                    $rodzic_id = null;
                }
            } catch (PDOException $e) {
                $rodzic_id = null;
            }
        }

        $godziny_oczekiwania_komentarz = empty($_SESSION['jest_adminem'])
            ? sprawdzKarencje($polaczenie, (int)$_SESSION['uzytkownik_id'], 'komentarz')
            : 0.0;

        $blad_komentarza   = '';
        $tresc_komentarza  = trim($_POST['tresc'] ?? '');
        if ($godziny_oczekiwania_komentarz > 0) {
            $blad_komentarza = '';
        } elseif (!empty($tresc_komentarza)) {
            if (strlen($tresc_komentarza) > 2000) {
                $blad_komentarza = 'Komentarz nie może przekraczać 2000 znaków.';
            } else {
                try {
                    $stmt = $polaczenie->prepare(
                        'INSERT INTO komentarze (wpis_id, autor_id, tresc, rodzic_id)
                         VALUES (:wpis_id, :autor_id, :tresc, :rodzic_id)'
                    );
                    $stmt->execute([
                        ':wpis_id'   => $wpis_id,
                        ':autor_id'  => $_SESSION['uzytkownik_id'],
                        ':tresc'     => $tresc_komentarza,
                        ':rodzic_id' => $rodzic_id,
                    ]);
                } catch (PDOException $e) {
                    error_log('Błąd dodawania komentarza: ' . $e->getMessage());
                    $blad_komentarza = 'Wystąpił błąd podczas dodawania komentarza. Spróbuj ponownie.';
                }
            }
        }

        if (empty($_SERVER['HTTP_HX_REQUEST'])) {
            if ($rodzic_id !== null) {
                header('Location: /komentarz/' . $rodzic_id);
            } else {
                header('Location: /wpis/' . $wpis_id);
            }
            exit;
        }

        try {
            $stmt = $polaczenie->prepare(
                'SELECT k.id, k.tresc, k.rodzic_id, k.usunieto, u.nazwa AS autor, k.autor_id, k.data_dodania,
                        COALESCE(SUM(g.wartosc), 0) AS wynik
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 LEFT JOIN glosy g ON g.komentarz_id = k.id
                 WHERE k.wpis_id = :wpis_id
                 GROUP BY k.id, k.tresc, k.rodzic_id, k.usunieto, u.nazwa, k.autor_id, k.data_dodania
                 ORDER BY k.data_dodania ASC'
            );
            $stmt->execute([':wpis_id' => $wpis_id]);
            $komentarze = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania komentarzy: ' . $e->getMessage());
            $komentarze = [];
        }

        $glosy_komentarzy = pobierzGlosyKomentarzy($polaczenie, (int)$_SESSION['uzytkownik_id'], $komentarze);

        echo renderujKomentarze($komentarze, $wpis_id, true, $blad_komentarza, $godziny_oczekiwania_komentarz, $glosy_komentarzy, !empty($_SESSION['jest_adminem']) || !empty($_SESSION['jest_moderatorem']), (int)$_SESSION['uzytkownik_id']);
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
            $stmt_autor = $polaczenie->prepare('SELECT autor_id FROM wpisy WHERE id = :id');
            $stmt_autor->execute([':id' => $wpis_id]);
            $wpis_autor_id = $stmt_autor->fetchColumn();
            if ($wpis_autor_id === false) {
                http_response_code(404);
                exit;
            }
            if ((int)$wpis_autor_id === (int)$_SESSION['uzytkownik_id']) {
                http_response_code(403);
                exit;
            }

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

            $stmt_glos = $polaczenie->prepare('SELECT wartosc FROM glosy WHERE uzytkownik_id = :u AND wpis_id = :w');
            $stmt_glos->execute([':u' => $_SESSION['uzytkownik_id'], ':w' => $wpis_id]);
            $glos_aktualny = (int)($stmt_glos->fetchColumn() ?: 0);

            echo renderujSekcjeGlosow('wpis', $wpis_id, (int)$wynik_raw, true, $glos_aktualny);
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
            $stmt_autor_k = $polaczenie->prepare('SELECT autor_id FROM komentarze WHERE id = :id');
            $stmt_autor_k->execute([':id' => $komentarz_id]);
            $komentarz_autor_id = $stmt_autor_k->fetchColumn();
            if ($komentarz_autor_id === false) {
                http_response_code(404);
                exit;
            }
            if ((int)$komentarz_autor_id === (int)$_SESSION['uzytkownik_id']) {
                http_response_code(403);
                exit;
            }

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

            $stmt_glos = $polaczenie->prepare('SELECT wartosc FROM glosy WHERE uzytkownik_id = :u AND komentarz_id = :k');
            $stmt_glos->execute([':u' => $_SESSION['uzytkownik_id'], ':k' => $komentarz_id]);
            $glos_aktualny = (int)($stmt_glos->fetchColumn() ?: 0);

            echo renderujSekcjeGlosow('komentarz', $komentarz_id, (int)$wynik_raw, true, $glos_aktualny);
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
                'SELECT w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa AS autor, w.autor_id, w.wynik, w.data_dodania, w.usunieto,
                        COUNT(k.id) AS liczba_komentarzy
                 FROM wpisy_z_wynikiem w
                 JOIN uzytkownicy u ON u.id = w.autor_id
                 LEFT JOIN komentarze k ON k.wpis_id = w.id
                 WHERE w.tresc ~* :pattern
                 GROUP BY w.id, w.tytul, w.tresc, w.link, w.rodzaj, u.nazwa, w.autor_id, w.wynik, w.data_dodania, w.usunieto
                 ORDER BY w.data_dodania DESC'
            );
            $stmt->execute([':pattern' => '(^|[^[:alnum:]_#])#' . $tag_nazwa . '([^[:alnum:]_]|$)']);
            $wpisy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania wpisów tagu: ' . $e->getMessage());
            $wpisy = [];
        }

        $glosy_uzytkownika_wpisy = isset($_SESSION['uzytkownik_id'])
            ? pobierzGlosyWpisow($polaczenie, (int)$_SESSION['uzytkownik_id'], $wpisy)
            : [];

        echo '<h1>Wpisy z tagiem #' . $tag_wyswietlany . '</h1>';

        if (empty($wpisy)) {
            echo '<p class="empty-state">Brak wpisów z tym tagiem.</p>';
        } else {
            echo '<ul class="card-list">';
            foreach ($wpisy as $wpis) {
                echo renderujKarteWpisu($wpis, isset($_SESSION['uzytkownik_id']), $glosy_uzytkownika_wpisy[(int)$wpis['id']] ?? 0, !empty($_SESSION['jest_adminem']) || !empty($_SESSION['jest_moderatorem']), (int)($_SESSION['uzytkownik_id'] ?? 0));
            }
            echo '</ul>';
        }
        break;
    case 'admin':
        if (!isset($_SESSION['uzytkownik_id']) || empty($_SESSION['jest_adminem'])) {
            http_response_code(403);
            echo '<h1>Panel administratora</h1>';
            echo '<p>Brak dostępu.</p>';
            break;
        }

        $komunikat_admina = '';
        $komunikat_moderatora = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcja_moderatora'])) {
            $nazwa_moderatora = trim($_POST['nazwa_moderatora'] ?? '');
            if ($nazwa_moderatora === '') {
                $komunikat_moderatora = 'Podaj nazwę użytkownika.';
            } else {
                $akcja = $_POST['akcja_moderatora'];
                if ($akcja === 'dodaj' || $akcja === 'usun') {
                    $nowa_wartosc_mod = $akcja === 'dodaj';
                    try {
                        $polaczenie->beginTransaction();
                        $stmt_check = $polaczenie->prepare('SELECT jest_adminem FROM uzytkownicy WHERE nazwa = :nazwa FOR UPDATE');
                        $stmt_check->execute([':nazwa' => $nazwa_moderatora]);
                        $wiersz = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        if ($wiersz === false) {
                            $polaczenie->rollBack();
                            $komunikat_moderatora = 'Nie znaleziono użytkownika o podanej nazwie.';
                        } elseif ($wiersz['jest_adminem']) {
                            $polaczenie->rollBack();
                            $komunikat_moderatora = 'Nie można zmieniać roli moderatora dla administratora.';
                        } else {
                            $stmt_mod = $polaczenie->prepare('UPDATE uzytkownicy SET jest_moderatorem = :wartosc WHERE nazwa = :nazwa');
                            $stmt_mod->execute([':wartosc' => $nowa_wartosc_mod ? 'true' : 'false', ':nazwa' => $nazwa_moderatora]);
                            $polaczenie->commit();
                            $komunikat_moderatora = $akcja === 'dodaj'
                                ? 'Użytkownik „' . htmlspecialchars($nazwa_moderatora, ENT_QUOTES, 'UTF-8') . '" został mianowany moderatorem.'
                                : 'Użytkownik „' . htmlspecialchars($nazwa_moderatora, ENT_QUOTES, 'UTF-8') . '" został pozbawiony roli moderatora.';
                        }
                    } catch (PDOException $e) {
                        if ($polaczenie->inTransaction()) $polaczenie->rollBack();
                        error_log('Błąd zmiany moderatora: ' . $e->getMessage());
                        $komunikat_moderatora = 'Wystąpił błąd podczas zmiany uprawnień moderatora.';
                    }
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nowa_wartosc = (isset($_POST['rejestracja_wlaczona']) && $_POST['rejestracja_wlaczona'] === '1') ? 'true' : 'false';
            $czas_wpisu       = max(0, (int)($_POST['minimalny_czas_wpisu']       ?? DOMYSLNY_CZAS_WPISU));
            $czas_komentarza  = max(0, (int)($_POST['minimalny_czas_komentarza']  ?? DOMYSLNY_CZAS_KOMENTARZA));
            try {
                $stmt_upd = $polaczenie->prepare("UPDATE konfiguracja SET wartosc = :wartosc WHERE klucz = 'rejestracja_wlaczona'");
                $stmt_upd->execute([':wartosc' => $nowa_wartosc]);
                $stmt_upd2 = $polaczenie->prepare("UPDATE konfiguracja SET wartosc = :wartosc WHERE klucz = 'minimalny_czas_wpisu'");
                $stmt_upd2->execute([':wartosc' => (string)$czas_wpisu]);
                $stmt_upd3 = $polaczenie->prepare("UPDATE konfiguracja SET wartosc = :wartosc WHERE klucz = 'minimalny_czas_komentarza'");
                $stmt_upd3->execute([':wartosc' => (string)$czas_komentarza]);
                $komunikat_admina = 'Konfiguracja została zapisana.';
            } catch (PDOException $e) {
                error_log('Błąd zapisu konfiguracji: ' . $e->getMessage());
                $komunikat_admina = 'Wystąpił błąd podczas zapisu konfiguracji.';
            }
        }

        $rejestracja_wlaczona_cfg    = false;
        $minimalny_czas_wpisu_cfg    = DOMYSLNY_CZAS_WPISU;
        $minimalny_czas_komentarza_cfg = DOMYSLNY_CZAS_KOMENTARZA;
        try {
            $stmt_cfg2 = $polaczenie->query("SELECT klucz, wartosc FROM konfiguracja WHERE klucz IN ('rejestracja_wlaczona','minimalny_czas_wpisu','minimalny_czas_komentarza')");
            foreach ($stmt_cfg2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['klucz'] === 'rejestracja_wlaczona')    $rejestracja_wlaczona_cfg    = ($row['wartosc'] === 'true');
                if ($row['klucz'] === 'minimalny_czas_wpisu')    $minimalny_czas_wpisu_cfg    = (int)$row['wartosc'];
                if ($row['klucz'] === 'minimalny_czas_komentarza') $minimalny_czas_komentarza_cfg = (int)$row['wartosc'];
            }
        } catch (PDOException $e) {
            error_log('Błąd odczytu konfiguracji: ' . $e->getMessage());
        }

        $moderatorzy = [];
        try {
            $stmt_mods = $polaczenie->query("SELECT nazwa FROM uzytkownicy WHERE jest_moderatorem = TRUE ORDER BY nazwa");
            $moderatorzy = $stmt_mods->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log('Błąd pobierania moderatorów: ' . $e->getMessage());
        }

        echo '<h1>Panel administratora</h1>';
        if ($komunikat_admina !== '') {
            echo '<p class="admin-msg">' . htmlspecialchars($komunikat_admina, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '<section class="admin-section">';
        echo '<form method="post" class="form-stack">';
        echo '<h2>Ustawienia rejestracji</h2>';
        echo '<label class="admin-label">';
        echo '<input type="checkbox" name="rejestracja_wlaczona" value="1"' . ($rejestracja_wlaczona_cfg ? ' checked' : '') . '>';
        echo ' Rejestracja włączona';
        echo '</label>';
        echo '<h2>Karencja dla nowych użytkowników</h2>';
        echo '<label class="admin-label">Minimalny czas przed dodaniem wpisu (godz.):';
        echo '<input type="number" name="minimalny_czas_wpisu" value="' . $minimalny_czas_wpisu_cfg . '" min="0" style="width:80px;margin-left:0.5rem">';
        echo '</label>';
        echo '<label class="admin-label">Minimalny czas przed dodaniem komentarza (godz.):';
        echo '<input type="number" name="minimalny_czas_komentarza" value="' . $minimalny_czas_komentarza_cfg . '" min="0" style="width:80px;margin-left:0.5rem">';
        echo '</label>';
        echo '<button type="submit" class="btn-primary">Zapisz</button>';
        echo '</form>';
        echo '</section>';

        echo '<section class="admin-section">';
        echo '<h2>Moderatorzy</h2>';
        if ($komunikat_moderatora !== '') {
            echo '<p class="admin-msg">' . htmlspecialchars($komunikat_moderatora, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '<form method="post" class="form-stack">';
        echo '<input type="hidden" name="akcja_moderatora" value="dodaj">';
        echo '<label class="admin-label">Nazwa użytkownika:';
        echo '<input type="text" name="nazwa_moderatora" placeholder="Nazwa użytkownika" style="margin-left:0.5rem">';
        echo '</label>';
        echo '<button type="submit" class="btn-primary">Dodaj moderatora</button>';
        echo '</form>';
        if (!empty($moderatorzy)) {
            echo '<h3 style="margin-top:1rem">Aktualni moderatorzy</h3>';
            echo '<ul class="moderator-list">';
            foreach ($moderatorzy as $mod) {
                echo '<li>';
                echo htmlspecialchars($mod, ENT_QUOTES, 'UTF-8');
                echo '<form method="post" style="display:inline;margin-left:0.5rem">';
                echo '<input type="hidden" name="akcja_moderatora" value="usun">';
                echo '<input type="hidden" name="nazwa_moderatora" value="' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit" class="btn-danger">Usuń</button>';
                echo '</form>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="margin-top:0.75rem;font-size:0.9rem">Brak moderatorów.</p>';
        }
        echo '</section>';
        break;
    case 'moderuj_wpis':
        ob_end_clean();
        if (!isset($_SESSION['uzytkownik_id']) || (empty($_SESSION['jest_adminem']) && empty($_SESSION['jest_moderatorem']))) {
            http_response_code(403);
            header('Location: /');
            exit;
        }

        $wpis_id = isset($_POST['wpis_id']) ? (int)$_POST['wpis_id'] : 0;
        $akcja   = $_POST['akcja'] ?? '';

        if ($wpis_id <= 0 || !in_array($akcja, ['usun', 'przywroc'], true)) {
            http_response_code(400);
            header('Location: /');
            exit;
        }

        try {
            $stmt = $polaczenie->prepare('UPDATE wpisy SET usunieto = :usunieto WHERE id = :id');
            $stmt->execute([':usunieto' => ($akcja === 'usun'), ':id' => $wpis_id]);
        } catch (PDOException $e) {
            error_log('Błąd moderacji wpisu: ' . $e->getMessage());
        }

        header('Location: /wpis/' . $wpis_id);
        exit;
    case 'moderuj_komentarz':
        ob_end_clean();
        if (!isset($_SESSION['uzytkownik_id']) || (empty($_SESSION['jest_adminem']) && empty($_SESSION['jest_moderatorem']))) {
            http_response_code(403);
            header('Location: /');
            exit;
        }

        $komentarz_id = isset($_POST['komentarz_id']) ? (int)$_POST['komentarz_id'] : 0;
        $akcja        = $_POST['akcja'] ?? '';

        if ($komentarz_id <= 0 || !in_array($akcja, ['usun', 'przywroc'], true)) {
            http_response_code(400);
            header('Location: /');
            exit;
        }

        try {
            $stmt = $polaczenie->prepare('UPDATE komentarze SET usunieto = :usunieto WHERE id = :id');
            $stmt->execute([':usunieto' => ($akcja === 'usun'), ':id' => $komentarz_id]);
        } catch (PDOException $e) {
            error_log('Błąd moderacji komentarza: ' . $e->getMessage());
        }

        try {
            $stmt_pobierz_wpis = $polaczenie->prepare('SELECT wpis_id FROM komentarze WHERE id = :id');
            $stmt_pobierz_wpis->execute([':id' => $komentarz_id]);
            $wpis_id_redirect = (int)($stmt_pobierz_wpis->fetchColumn() ?: 0);
        } catch (PDOException $e) {
            $wpis_id_redirect = 0;
        }

        if ($wpis_id_redirect > 0) {
            header('Location: /wpis/' . $wpis_id_redirect);
        } else {
            header('Location: /');
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
        header('Location: /logowanie');
        exit;
    case 'weryfikuj_email':
        $token_wpisany = trim($_GET['token'] ?? '');
        if ($token_wpisany === '') {
            echo '<h1>Weryfikacja email</h1>';
            echo '<p>Nieprawidłowy lub brakujący token weryfikacyjny.</p>';
            break;
        }
        try {
            $stmt_tok = $polaczenie->prepare(
                'SELECT t.id, t.uzytkownik_id, t.data_wygasniecia, u.email_zweryfikowany
                 FROM tokeny_weryfikacji t
                 JOIN uzytkownicy u ON u.id = t.uzytkownik_id
                 WHERE t.token = :token'
            );
            $stmt_tok->execute([':token' => $token_wpisany]);
            $wiersz_tokenu = $stmt_tok->fetch(PDO::FETCH_ASSOC);

            if (!$wiersz_tokenu) {
                echo '<h1>Weryfikacja email</h1>';
                echo '<p>Nieprawidłowy lub nieistniejący link weryfikacyjny.</p>';
                break;
            }

            if ($wiersz_tokenu['email_zweryfikowany']) {
                echo '<h1>Weryfikacja email</h1>';
                echo '<p>Adres email został już wcześniej zweryfikowany. Możesz się <a href="/logowanie">zalogować</a>.</p>';
                break;
            }

            if (new DateTime('now') > new DateTime($wiersz_tokenu['data_wygasniecia'])) {
                $stmt_del = $polaczenie->prepare('DELETE FROM tokeny_weryfikacji WHERE id = :id');
                $stmt_del->execute([':id' => $wiersz_tokenu['id']]);
                echo '<h1>Weryfikacja email</h1>';
                echo '<p>Link weryfikacyjny wygasł. Skontaktuj się z administratorem serwisu.</p>';
                break;
            }

            $stmt_ver = $polaczenie->prepare('UPDATE uzytkownicy SET email_zweryfikowany = TRUE WHERE id = :id');
            $stmt_ver->execute([':id' => $wiersz_tokenu['uzytkownik_id']]);
            $stmt_del2 = $polaczenie->prepare('DELETE FROM tokeny_weryfikacji WHERE id = :id');
            $stmt_del2->execute([':id' => $wiersz_tokenu['id']]);

            echo '<h1>Weryfikacja email</h1>';
            echo '<p>Adres email został pomyślnie zweryfikowany. Możesz się teraz <a href="/logowanie">zalogować</a>.</p>';
        } catch (PDOException $e) {
            error_log('Błąd weryfikacji email: ' . $e->getMessage());
            echo '<h1>Weryfikacja email</h1>';
            echo '<p>Wystąpił błąd podczas weryfikacji. Spróbuj ponownie.</p>';
        }
        break;
    case 'komentarz':
        $komentarz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($komentarz_id <= 0) {
            echo '<p>Nieprawidłowy identyfikator komentarza.</p>';
            break;
        }

        try {
            $stmt = $polaczenie->prepare(
                'SELECT k.id, k.tresc, k.rodzic_id, k.wpis_id, k.usunieto, u.nazwa AS autor, k.autor_id, k.data_dodania,
                        COALESCE(SUM(g.wartosc), 0) AS wynik
                 FROM komentarze k
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 LEFT JOIN glosy g ON g.komentarz_id = k.id
                 WHERE k.id = :id
                 GROUP BY k.id, k.tresc, k.rodzic_id, k.wpis_id, k.usunieto, u.nazwa, k.autor_id, k.data_dodania'
            );
            $stmt->execute([':id' => $komentarz_id]);
            $komentarz = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania komentarza: ' . $e->getMessage());
            $komentarz = null;
        }

        if (!$komentarz) {
            echo '<p>Nie znaleziono komentarza.</p>';
            break;
        }

        $wpis_id_k       = (int)$komentarz['wpis_id'];
        $zalogowany_k    = isset($_SESSION['uzytkownik_id']);
        $jest_mod_k      = !empty($_SESSION['jest_adminem']) || !empty($_SESSION['jest_moderatorem']);
        $usunieto_k      = !empty($komentarz['usunieto']);
        $jest_autorem_k  = $zalogowany_k && (int)($_SESSION['uzytkownik_id'] ?? 0) === (int)($komentarz['autor_id'] ?? 0);

        echo '<p><a href="/wpis/' . $wpis_id_k . '">← Wróć do wpisu</a></p>';

        $k_autor = htmlspecialchars($komentarz['autor'], ENT_QUOTES, 'UTF-8');
        $k_tresc = htmlspecialchars($komentarz['tresc'], ENT_QUOTES, 'UTF-8');
        $k_data  = htmlspecialchars(date('d.m.Y H:i', strtotime($komentarz['data_dodania'])), ENT_QUOTES, 'UTF-8');
        $k_wynik = (int)($komentarz['wynik'] ?? 0);

        $glos_k = 0;
        if ($zalogowany_k) {
            try {
                $stmt_gk = $polaczenie->prepare('SELECT wartosc FROM glosy WHERE uzytkownik_id = :u AND komentarz_id = :k');
                $stmt_gk->execute([':u' => $_SESSION['uzytkownik_id'], ':k' => $komentarz_id]);
                $glos_k = (int)($stmt_gk->fetchColumn() ?: 0);
            } catch (PDOException $e) {
                error_log('Błąd pobierania głosu: ' . $e->getMessage());
            }
        }

        echo '<div class="card card-layout card-layout--detail">';
        echo renderujSekcjeGlosow('komentarz', $komentarz_id, $k_wynik, $zalogowany_k, $glos_k, $jest_autorem_k);
        echo '<div class="card-content">';
        echo '<div class="card-header">';
        echo '<strong class="card-author">' . $k_autor . '</strong>';
        echo '<span class="card-date">' . $k_data . '</span>';
        if ($jest_mod_k) {
            if ($usunieto_k) {
                echo '<form method="post" action="/moderuj_komentarz" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="komentarz_id" value="' . $komentarz_id . '">'
                   . '<input type="hidden" name="akcja" value="przywroc">'
                   . '<button type="submit" class="btn-mod btn-mod--przywroc">MODERACJA: PRZYWRÓĆ</button>'
                   . '</form>';
            } else {
                echo '<form method="post" action="/moderuj_komentarz" style="display:inline;margin-left:auto">'
                   . '<input type="hidden" name="komentarz_id" value="' . $komentarz_id . '">'
                   . '<input type="hidden" name="akcja" value="usun">'
                   . '<button type="submit" class="btn-mod btn-mod--usun">MODERACJA: USUŃ</button>'
                   . '</form>';
            }
        }
        echo '</div>';
        if ($usunieto_k && !$jest_mod_k) {
            echo '<div class="card-body article-body"><em class="wpis-usuniety-info">Ten komentarz został usunięty przez moderatora</em></div>';
        } else {
            echo '<div class="card-body article-body' . ($usunieto_k ? ' wpis-usuniety' : '') . '">' . nl2br(parsujTagi($k_tresc)) . '</div>';
        }
        echo '</div>';
        echo '</div>';

        try {
            $stmt = $polaczenie->prepare(
                'WITH RECURSIVE poddrzewo AS (
                     SELECT k.id FROM komentarze k WHERE k.rodzic_id = :komentarz_id
                     UNION ALL
                     SELECT k.id FROM komentarze k JOIN poddrzewo p ON k.rodzic_id = p.id
                 )
                 SELECT k.id, k.tresc, k.rodzic_id, k.usunieto, u.nazwa AS autor, k.data_dodania,
                        COALESCE(SUM(g.wartosc), 0) AS wynik
                 FROM komentarze k
                 JOIN poddrzewo pd ON pd.id = k.id
                 JOIN uzytkownicy u ON u.id = k.autor_id
                 LEFT JOIN glosy g ON g.komentarz_id = k.id
                 GROUP BY k.id, k.tresc, k.rodzic_id, k.usunieto, u.nazwa, k.data_dodania
                 ORDER BY k.data_dodania ASC'
            );
            $stmt->execute([':komentarz_id' => $komentarz_id]);
            $wszystkie_k = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Błąd pobierania odpowiedzi: ' . $e->getMessage());
            $wszystkie_k = [];
        }

        $glosy_k = $zalogowany_k
            ? pobierzGlosyKomentarzy($polaczenie, (int)$_SESSION['uzytkownik_id'], $wszystkie_k)
            : [];

        $dzieci_k = [];
        foreach ($wszystkie_k as $k) {
            $rodzic = ($k['rodzic_id'] !== null) ? (int)$k['rodzic_id'] : null;
            if ($rodzic !== null) {
                $dzieci_k[$rodzic][] = $k;
            }
        }

        echo '<div id="komentarze-sekcja">';
        echo '<section class="comments-section">';
        if (!empty($dzieci_k[$komentarz_id])) {
            echo '<ul class="comment-list">';
            foreach ($dzieci_k[$komentarz_id] as $dziecko) {
                echo renderujElementKomentarza($dziecko, $zalogowany_k, $glosy_k, $dzieci_k, 0, $jest_mod_k);
            }
            echo '</ul>';
        }
        echo '</section>';

        if ($zalogowany_k) {
            $godziny_oczekiwania_k = empty($_SESSION['jest_adminem'])
                ? sprawdzKarencje($polaczenie, (int)$_SESSION['uzytkownik_id'], 'komentarz')
                : 0.0;
            if ($godziny_oczekiwania_k > 0) {
                echo '<p class="empty-state">Możesz dodać komentarz za ' . formatujCzasOczekiwania($godziny_oczekiwania_k) . '.</p>';
            } else {
                echo '<form method="post" action="/dodaj_komentarz/' . $wpis_id_k . '" class="form-stack form-stack--comment">';
                echo '<input type="hidden" name="rodzic_id" value="' . $komentarz_id . '">';
                echo '<textarea name="tresc" placeholder="Dodaj odpowiedź..." rows="3" maxlength="2000" required></textarea>';
                echo '<button type="submit" class="btn-primary">Wyślij odpowiedź</button>';
                echo '</form>';
            }
        } else {
            echo '<p class="empty-state"><a href="/logowanie">Zaloguj się</a>, aby odpowiedzieć.</p>';
        }
        echo '</div>';
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
            padding: 8px 4px;
        }

        /* ── Card two-column layout ── */
        .card-layout {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .card-layout--detail {
            padding: 8px 4px;
            margin-bottom: 0.5rem;
        }

        .card-votes {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            min-width: 32px;
            flex-shrink: 0;
            padding-top: 1px;
        }

        .vote-icon {
            font-size: 0.65rem;
            color: #999;
            line-height: 1;
        }

        .card-content {
            flex: 1;
            min-width: 0;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 3px;
            font-size: 0.78rem;
        }

        .card-author {
            color: #000;
            font-weight: bold;
        }

        .card-date {
            color: #888;
        }

        .card-body {
            margin-bottom: 4px;
        }

        .card-title {
            font-size: 0.95rem;
            text-decoration: none;
            color: #000;
        }

        .card-title:hover { text-decoration: underline; }
        .card-title:visited { color: #444; }

        .card-footer {
            display: flex;
            gap: 0.75rem;
            font-size: 0.78rem;
            margin-top: 4px;
        }

        .card-meta-link {
            color: #555;
            text-decoration: none;
        }

        .card-meta-link:hover { color: #000; text-decoration: underline; }
        .card-meta-link--reply { text-decoration: underline; }

        /* ── Score & Vote buttons ── */
        .score { font-weight: bold; font-size: 0.85rem; }

        .btn-vote {
            cursor: pointer;
            font-size: 0.65rem;
            font-family: Verdana, Geneva, sans-serif;
            border: none;
            background: none;
            color: #999;
            padding: 0;
            line-height: 1;
        }

        .btn-vote:hover { color: #000; }
        .btn-vote.active { color: #000; }

        /* ── Article (single post) ── */
        .article-body {
            line-height: 1.6;
        }

        .article-link {
            display: inline-block;
            margin-bottom: 0.5rem;
            color: #000;
            font-size: 0.85rem;
            word-break: break-all;
            text-decoration: underline;
        }

        /* ── Comments ── */
        .comments-section { margin-top: 0.5rem; }

        .comment-list {
            list-style: none;
        }

        .comment-list--zagniezdzone {
            list-style: none;
            margin-left: 2rem;
        }

        .comment-list--zagniezdzone > .comment-item {
            margin-left: 0;
            padding-left: 0;
        }

        .comment-item {
            padding: 8px 4px;
            padding-left: 1.5rem;
            margin-left: 16px;
        }

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

        /* ── Admin panel ── */
        .admin-section { margin-top: 1rem; }
        .admin-msg { margin-bottom: 0.75rem; border: 1px solid #999; padding: 4px 8px; font-size: 0.85rem; }
        .admin-label { font-size: 0.9rem; display: flex; align-items: center; gap: 0.4rem; }
        .btn-danger { font-size: 0.8rem; padding: 2px 6px; background: #fff; border: 1px solid #c00; color: #c00; cursor: pointer; }
        .btn-danger:hover { background: #c00; color: #fff; }
        .moderator-list { list-style: none; padding: 0; margin: 0.5rem 0 0; }
        .moderator-list li { padding: 3px 0; font-size: 0.9rem; }

        /* ── Moderacja wpisów ── */
        .btn-mod { font-size: 0.75rem; padding: 1px 4px; cursor: pointer; border: none; background: none; font-family: Verdana, Geneva, sans-serif; }
        .btn-mod--usun { color: #c00; }
        .btn-mod--usun:hover { text-decoration: underline; }
        .btn-mod--przywroc { color: #070; }
        .btn-mod--przywroc:hover { text-decoration: underline; }
        .wpis-usuniety { text-decoration: line-through; }
        .wpis-usuniety:hover { text-decoration: none; }
        .wpis-usuniety a { text-decoration: line-through; }
        .wpis-usuniety:hover a { text-decoration: none; }
        .wpis-usuniety-info { color: #888; font-style: italic; font-size: 0.9rem; }
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
                <?php if (!empty($_SESSION['jest_adminem'])): ?>
                    <a href="/admin">Admin</a>
                    <span class="nav-sep">|</span>
                <?php endif; ?>
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
