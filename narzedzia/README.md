# Typer na cyber_Folks (bez SSH)

Wszystko robi jedno zadanie cron i paczka ZIP z komputera. Ścieżki: `LOGIN` to login w DirectAdmin, `DOMENA` to domena strony.

## Pierwsze wgranie
1. **Panel**: wersja PHP **8.3+** dla domeny (rozszerzenia `pdo_mysql`, `mbstring`, `intl`, `gmp` lub `bcmath`), nowa baza MySQL, SSL (Let's Encrypt) i skrzynka pocztowa.
2. **Komputer**: dwuklik `narzedzia\paczka-na-serwer.bat`. Na pulpicie, w folderze `typer-na-serwer`, powstanie `typer-kod.zip`.
3. **Menedżer plików**: utwórz folder `domains/DOMENA/typer` (obok `public_html`, nie w nim), wgraj tam `typer-kod.zip` i rozpakuj go w tym folderze.
4. Skopiuj `typer/narzedzia/env-serwer.txt` jako `typer/.env` i uzupełnij linie `UZUPELNIJ` (baza, adres strony, poczta).
5. **Cron** (Zadania Cron w DirectAdmin), co minutę `* * * * *`:
   ```
   bash /home/LOGIN/domains/DOMENA/typer/narzedzia/instaluj-na-serwerze.sh
   ```
6. Po 1–2 minutach sprawdź `typer/storage/logs/instalacja.log`. Na końcu powinno być `=== GOTOWE ===`.

Skrypt sam: poprawia uprawnienia (foldery 755, pliki 644), podpina `public_html` pod `typer/public` (stary folder zostaje jako `public_html_stary_…`), tworzy `APP_KEY` i klucze push (VAPID), uruchamia migracje, raz wpisuje dane startowe (role, admin, boty, kadra, ikony trofeów, newsy), robi `storage:link` i `optimize`.

Admin z danych startowych: `marcin.molak@gmail.com`, hasło `password`. **Zmień je od razu po zalogowaniu.**

## Aktualizacja
1. `git pull`, potem dwuklik `paczka-na-serwer.bat`.
2. Wgraj `typer-kod.zip` do `domains/DOMENA/typer` i rozpakuj z nadpisaniem. Nie importuj `typer.sql`, bo nadpisałby typy i wyniki graczy.
3. Cron w ciągu minuty zauważy nową paczkę (numer w `bootstrap/wersja-paczki.txt`) i sam uruchomi migracje oraz `optimize`.

Zmiana w `.env` też uruchamia instalację przy następnej minucie, więc nie trzeba już ręcznie czyścić cache.

## Gdy coś nie działa
- Błąd 403 lub 500 zaraz po rozpakowaniu: poczekaj minutę, aż cron poprawi uprawnienia.
- W logu „nie znalazłem PHP 8.3”: ustaw wersję PHP w panelu albo dopisz przed `bash` w cronie `PHP=/ścieżka/do/php83`.
- Inne błędy: wklej `storage/logs/instalacja.log` Claude.
