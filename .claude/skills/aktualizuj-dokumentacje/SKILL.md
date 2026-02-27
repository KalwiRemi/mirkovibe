---
name: aktualizuj-dokumentacje
description: Aktualizuje plik docs/ficzery.md gdy ficzery aplikacji się zmieniają. Używaj tego skilla zawsze gdy dodajesz, usuwasz lub modyfikujesz ficzery w index.php, setup.sql lub migrate.sql.
---

Ten skill służy do utrzymywania aktualności dokumentacji ficzerów w pliku `docs/ficzery.md`.

Użytkownik wprowadził zmiany w aplikacji (nowy ficzer, usunięty ficzer, zmiana istniejącego ficzera). Twoim zadaniem jest zaktualizowanie pliku `docs/ficzery.md` tak, aby odzwierciedlał aktualny stan aplikacji.

## Kroki

1. **Przeczytaj aktualny stan aplikacji** – przejrzyj plik `index.php` (szczególnie tablicę `$dozwolone_strony` i bloki `case` w switchu) oraz `setup.sql` i `migrate.sql` (tabele, widoki, funkcje SQL), żeby zrozumieć wszystkie dostępne ficzery.

2. **Przeczytaj aktualną dokumentację** – otwórz `docs/ficzery.md` i zapoznaj się z tym, co już jest udokumentowane.

3. **Zidentyfikuj różnice** – ustal co się zmieniło:
   - Nowe ficzery (nowe strony, nowe akcje, nowe pola formularzy)
   - Usunięte ficzery
   - Zmodyfikowane ficzery (zmienione reguły walidacji, nowe pola, zmienione zachowanie)

4. **Zaktualizuj `docs/ficzery.md`** – edytuj plik zachowując istniejący styl i strukturę:
   - Każdy ficzer opisany jako osobna sekcja z nagłówkiem `##`
   - Opis po polsku, zwięzły i konkretny
   - URL dostępu tam gdzie dotyczy (format: `` `index.php?strona=nazwa` ``)
   - Wymagania i ograniczenia (np. minimalna długość, wymagane pola)
   - Informacja czy ficzer wymaga zalogowania
   - Wzmianka o htmx jeśli akcja odbywa się bez przeładowania strony

## Zasady pisania dokumentacji

- Pisz po polsku.
- Bądź zwięzły – opisuj co ficzer robi, nie jak jest zaimplementowany.
- Zachowaj numerację sekcji spójną z kolejnością ficzerów.
- Sekcja "Techniczny stack" na końcu pliku – aktualizuj tylko jeśli stack się zmienił.
- Nie usuwaj istniejących sekcji bez wyraźnego polecenia.
