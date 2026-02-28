# Ficzery Mirkovibe

Mirkovibe to prosta alternatywa dla Wykopu i Reddita. Poniżej opisano wszystkie dostępne ficzery aplikacji.

---

## 1. Rejestracja

Nowi użytkownicy mogą założyć konto podając:
- **Nazwę użytkownika** – co najmniej 3 znaki, unikalna w systemie.
- **Adres email** – wymagany, unikalny w systemie; służy do weryfikacji konta.
- **Hasło** – co najmniej 6 znaków (wpisywane dwukrotnie w celu potwierdzenia).

Hasła są bezpiecznie haszowane w bazie danych przy użyciu algorytmu bcrypt (pgcrypto).

Po rejestracji na podany adres email wysyłany jest link weryfikacyjny ważny przez 24 godziny. Konto jest nieaktywne do momentu kliknięcia linku. Zalogowanie się przed weryfikacją jest niemożliwe.

Domyślnie rejestracja jest **wyłączona**. Gdy jest wyłączona, użytkownicy wchodzący na stronę rejestracji widzą komunikat „Rejestracja wyłączona" i nie mogą założyć konta. Wyjątkiem jest użytkownik o loginie zgodnym ze zmienną środowiskową `NAZWA_ADMINISTRATORA` – taki użytkownik może zarejestrować się zawsze i po rejestracji automatycznie otrzymuje uprawnienia administratora oraz ma konto od razu aktywne (bez konieczności weryfikacji email).

Zmienna środowiskowa `APP_URL` określa bazowy adres URL serwisu używany do generowania linków weryfikacyjnych (np. `https://example.com`). Jeśli nie jest ustawiona, adres jest wyznaczany automatycznie na podstawie nagłówka HTTP.

**URL:** `/rejestracja`

---

## 1a. Weryfikacja adresu email

Po kliknięciu linku weryfikacyjnego wysłanego podczas rejestracji, serwis weryfikuje token i aktywuje konto użytkownika. Token jest jednorazowy i wygasa po 24 godzinach. Po pomyślnej weryfikacji użytkownik może się zalogować.

**URL:** `/weryfikuj_email?token={token}`

---

## 2. Logowanie

Zarejestrowani użytkownicy mogą się zalogować podając nazwę użytkownika i hasło. Po pomyślnym logowaniu sesja jest odświeżana w celu bezpieczeństwa.

**URL:** `/logowanie`

---

## 3. Wylogowanie

Zalogowany użytkownik może się wylogować. Sesja jest w pełni niszczona po stronie serwera.

**URL:** `/wyloguj`

---

## 4. Lista wpisów (strona główna)

Strona główna wyświetla listę wszystkich wpisów posortowanych od najnowszych. Każdy wpis na liście pokazuje:
- Dla **wpisu**: fragment treści (do 120 znaków) jako link do strony wpisu
- Dla **linku**: oznaczenie `L`, tytuł jako link do strony wpisu oraz domenę zewnętrznego URL
- Autora
- Aktualny wynik głosowania
- Przyciski do głosowania (tylko dla zalogowanych użytkowników)
- Liczbę komentarzy
- Datę dodania

Wpisy są stronicowane – na jednej stronie wyświetla się **10 wpisów**. Nawigacja między stronami odbywa się przez paginację.

**URL:** `/` (domyślna), kolejne strony: `/glowna/{numer}`

---

## 5. Dodawanie wpisu

Zalogowany użytkownik może dodać jeden z dwóch rodzajów wpisów. Na stronie dostępny jest przełącznik między trybami:

> **Uwaga:** Nowi użytkownicy muszą poczekać określony czas po rejestracji, zanim będą mogli dodać wpis. Domyślnie jest to **12 godzin**. Jeśli czas jeszcze nie upłynął, wyświetlana jest informacja „Możesz dodać wpis za X godzin".

### Wpis
Zwykły wpis tekstowy. Wymagane:
- **Treść** – wymagana (bez tytułu i URL).

### Link
Link do zewnętrznej strony. Wymagane:
- **Tytuł** – wymagany.
- **URL** – wymagany (musi być adresem HTTP lub HTTPS).
- **Tagi** – wymagane (np. `#technologia #muzyka`); przechowywane jako hashtagi.

**URL:** `/dodaj`

---

## 6. Strona wpisu

Każdy wpis ma własną stronę. Wyświetlana zawartość zależy od rodzaju wpisu:

**Wpis:**
- Treść wpisu

**Link:**
- Tytuł wpisu (z oznaczeniem `L`)
- Klikalny URL zewnętrzny (otwierany w nowej karcie)
- Tagi powiązane z linkiem

Wspólne dla obu rodzajów:
- Metadane: autor, wynik głosowania, data dodania
- Przyciski do głosowania (tylko dla zalogowanych użytkowników)
- Sekcja komentarzy

**URL:** `/wpis/{id}`

---

## 7. Komentarze

Zalogowani użytkownicy mogą dodawać komentarze do wpisów. Komentarz:
- Musi mieć treść.
- Nie może przekraczać **2000 znaków**.

> **Uwaga:** Nowi użytkownicy muszą poczekać określony czas po rejestracji, zanim będą mogli dodać komentarz. Domyślnie jest to **1 godzina**. Jeśli czas jeszcze nie upłynął, wyświetlana jest informacja „Możesz dodać komentarz za X godzin".

Lista komentarzy jest wyświetlana chronologicznie (od najstarszego). Każdy komentarz pokazuje autora, datę, treść oraz aktualny wynik głosowania.

Dodawanie komentarzy odbywa się bez przeładowania strony dzięki **htmx**.

---

## 8. Głosowanie na wpisy

Zalogowani użytkownicy mogą głosować na wpisy przyciskami `+` i `−`. Każdy użytkownik może oddać jeden głos na dany wpis (zmiana głosu jest możliwa). Wynik jest aktualizowany bez przeładowania strony dzięki **htmx**.

---

## 9. Głosowanie na komentarze

Zalogowani użytkownicy mogą głosować na komentarze przyciskami `+` i `−`. Każdy użytkownik może oddać jeden głos na dany komentarz (zmiana głosu jest możliwa). Wynik jest aktualizowany bez przeładowania strony dzięki **htmx**.

---

## 10. Tagi

Użytkownicy mogą oznaczać wpisy i komentarze tagami, wpisując `#nazwatagu` w treści. Tagi są automatycznie parsowane podczas wyświetlania i zamieniane na klikalne linki prowadzące do strony tagu.

Strona tagu wyświetla wszystkie wpisy, których treść zawiera dany tag, posortowane od najnowszych. Prezentacja jest identyczna jak na stronie głównej (tytuł, autor, wynik, komentarze, data).

**URL strony tagu:** `/tag/{nazwatagu}`

---

## 11. Panel administratora

Administrator to specjalny rodzaj użytkownika z rozszerzonymi uprawnieniami. Link do panelu administratora jest widoczny w nawigacji wyłącznie dla administratora.

W panelu administrator może:
- **Włączyć lub wyłączyć rejestrację** – checkbox „Rejestracja włączona". Gdy rejestracja jest wyłączona, strona `/rejestracja` wyświetla komunikat „Rejestracja wyłączona" dla zwykłych użytkowników.
- **Ustawić minimalny czas przed dodaniem wpisu** – pole liczbowe „Minimalny czas przed dodaniem wpisu (godz.)". Domyślnie **12 godzin**.
- **Ustawić minimalny czas przed dodaniem komentarza** – pole liczbowe „Minimalny czas przed dodaniem komentarza (godz.)". Domyślnie **1 godzina**.

Dostęp do panelu wymaga zalogowania jako administrator. Próba wejścia bez uprawnień zwraca błąd 403.

**URL:** `/admin`

---

## Techniczny stack

- **PHP** – logika aplikacji (jeden plik: `index.php`)
- **PostgreSQL** – baza danych (logika w bazie: funkcje, widoki)
- **htmx** – dynamiczne akcje bez przeładowania strony
- **Apache mod_rewrite** – przyjazne adresy URL (np. `/wpis/1` zamiast `index.php?strona=wpis&id=1`)
