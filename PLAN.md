# Plan poprawek #LechTYPER

V = zrobione, X = do zrobienia. Lista rośnie w trakcie pracy.

### Wdrożenie na lechtyper.pl

| Stan | Zadanie |
|:----:|---------|
| V | Narzędzia do wgrywania bez SSH (`narzedzia/paczka-na-serwer.bat`, cron `instaluj-na-serwerze.sh`) |
| V | Pierwsza instalacja na serwerze (baza MariaDB 10.11 na porcie 3308, migracje, dane startowe) |
| X | Błąd 500 „Unable to find component”: paczka bez ⚡ w nazwach plików (poprawka gotowa, czeka na wgranie i potwierdzenie) |
| V | Scalić poprawkę ⚡ do `master` |

### W trakcie

| Stan | Zadanie |
|:----:|---------|
| V | Panel newsów (`dashboard/news`): wyszukiwarka, filtry i sortowanie jako przyciski |
| V | Edytor treści newsów: formatowanie, ikony social mediów, obrazek z szerokością 25–100% i wyrównaniem |
| V | Admin zawsze premium |
| V | Obrazek na sociale (premium): tabele, kolejki ligowe, rundy pucharu, grupy europejskie, newsy, panele na /tips (w tym „Moja kadra”) |
| X | Sprawdzić newsy i obrazek na sociale na lechtyper.pl po wgraniu paczki |

### Następne

| Stan | Zadanie |
|:----:|---------|
| V | Adres panelu `/panel/...` zamiast `/dashboard/...` (stare linki przekierowują), nazwa „Panel” |
| V | Ustawienia konta po samym zalogowaniu; niepotwierdzony e-mail potwierdza się kodem w pasku w ustawieniach (bez wypadania poza stronę) |
| V | Strona „Potwierdź e-mail” w zwykłym układzie strony |
| V | Ustawienia z panelu admina zostają w układzie panelu (`/panel/settings/...`), ze strony w układzie strony |
| V | Stopka na każdej stronie: teraz na stronie (zalogowani i goście), gracze, oddane typy, rozegrane kolejki, linki i Biuro skarg i wniosków |
| V | Biuro skarg i wniosków przeniesione z dużego baneru na stronie głównej do stopki (zostaje też w menu „Więcej”) |
| V | Klub użytkownika: oko z liczbą wyświetleń i niebieskie serduszko z liczbą polubień (migracja `team_likes`) |
| V | Brakujące polskie tłumaczenia w ustawieniach i przy resecie hasła (Bezpieczeństwo, Wygląd, zmiana hasła, usuwanie konta) |
| V | Bez potwierdzonego maila nie można typować (bez zmian, dodany test) |
| V | `dashboard/office`: filtry stanu i rodzaju jako przyciski z licznikami |
| V | Filtry jako przyciski (komponent `x-ui.filter-pills`): newsy, sezony, wpłaty, zmiany nazw, użytkownicy (role), kadra Lecha (pozycje), typy i wyniki w panelu (kolejki), Hall of Fame, Gwiazdy |
| X | Wybór sezonu nadal jako lista rozwijana (sezonów przybywa); do decyzji, czy też na przyciski |

### Zasady gry

| Stan | Zadanie |
|:----:|---------|
| V | Status Inactive: 5 kolejek z rzędu bez typu (tylko kolejki z meczem gracza), sprawdzane przy zakończeniu sezonu |
| V | Ważne premium chroni przed statusem Inactive (Admin też) |
| V | Seria kolejek bez typu przechodzi na następny sezon (nowy sezon jej nie zeruje) |

### Płatności (odłożone na później)

| Stan | Zadanie |
|:----:|---------|
| V | Kod Przelewy24, cennik premium, cegiełki i panel „Wpłaty” (etap 15) |
| X | Konto Przelewy24: dane sprzedawcy (Merchant ID, POS ID, CRC, klucz API) w `.env` na serwerze |
| X | Test płatności w trybie sandbox na lechtyper.pl, potem przełączenie na produkcję |
| X | Ustalić, czego jeszcze brakuje w płatnościach (do dyskusji) |

### Wygląd (do zaplanowania i dyskusji)

| Stan | Zadanie |
|:----:|---------|
| V | Jedna spokojna czcionka bezszeryfowa Inter zamiast Barlow Condensed; najgrubsza waga 700 zamiast 900; mniejsze odstępy liter |
| V | Menu bez wersalików; banery i belki w stonowanym niebieskim zamiast przejścia w czerń; jaskrawe plakietki (zielone, czerwone, żółte) w jasnych odcieniach |
| X | Ocena po zmianach: co jeszcze jest za mocne (zrzuty ekranu) |
| V | „Aktualności” w prawej kolumnie: jedna karta, miniatura (obrazek z newsa albo data), tytuł, „x dni temu” |
| V | Przycisk „Udostępnij” wyraźniejszy (ikona z napisem) i na stronie głównej: najbliższa i ostatnia kolejka, ostatni mistrzowie |
| V | Kafelek newsa klikalny w całości (otwiera pełną wiadomość), dyskretne uniesienie, cień i strzałka „Czytaj dalej” po najechaniu |
| V | „Udostępnij”: podgląd obrazka z przyciskami Pobierz i Udostępnij (naprawa: przeglądarka blokowała udostępnianie) |
| V | Menu „Więcej”: kafelki z ikonami w siatce (bez przewijania), skromny baner instalacji, motyw jako ikony, język na dole jako przyciski z flagami; „Panel” tylko dla osób z dostępem do panelu |
| V | Duże przyciski (Zmień typ, Zainstaluj…) bez wersalików i bardzo grubej czcionki |
| V | Kolejki na /tips: na komputerze wszystkie w jednym rzędzie bez suwaka (wybrana szersza), na telefonie przesuwane palcem bez widocznego paska |
| V | Statystyki w stopce: ikony, liczby nabijające się od zera, pulsująca kropka „na żywo”, pasek postępu sezonu, uniesienie po najechaniu |
| X | Statystyki w stopce: szczegóły do ustalenia |
| V | Odliczanie na stronie głównej jak tablica klapkowa na dworcu (cyfry się przewracają) |
| V | Panel „Kolory strony” (`/panel/site-colors`, tylko Admin): barwy klubu, belki i banery, jasny motyw, dobrze/źle, klapki tablicy; podgląd na żywo, przywracanie domyślnych |
| X | Kolejne kolory do panelu (ciemny motyw, przyciski, tabele) – w miarę potrzeb |
| V | Powrót do granatu „Bułgarska nocą” na belkach i banerach (czcionka Inter zostaje) |
| V | /tips: wybór typu rozgrywek i zawodnicy bez wersalików i grubej czcionki |
| V | Stopka jak w jokerpila.pl: ciemny granat na całą szerokość, „Na skróty”, Biuro skarg i wniosków, „Projekt i wykonanie”, licznik odwiedzin (osoby teraz, goście dziś, wizyty, odsłony; co 15 s) |
| V | Zapisywanie odsłon stron bez IP i ciasteczek (`page_views`, 400 dni, bez robotów, panelu i adminów) |
| V | Klapki tablicy: osobne połówki, szczelina i zawiasy |
| V | Dymek (toast) nad menu zamiast pod nim; błąd „Udostępnij” pokazuje przyczynę |
| V | „Udostępnij”: błąd „error” naprawiony (atrybuty Livewire i Alpine w kopii do zdjęcia); sam znaczek bez napisu |
| X | Optymalizacja pod telefony (do przejrzenia na zrzutach z telefonu) |
| X | Nowoczesny i spójny wygląd panelu administratora |
| X | Każdy element strony konfigurowalny (w panelu: widoczność, kolejność, teksty sekcji); kolory już są („Kolory strony”) |

### Do zaplanowania i dyskusji

Pytania z propozycjami: `DYSKUSJA.md`.

| Stan | Zadanie |
|:----:|---------|
| V | Statystyki odwiedzin w panelu (`/panel/statistics`): wizyty, odsłony, porównanie z poprzednim okresem, wizyty dzień po dniu, działy, źródła wejść, urządzenia, godziny i dni tygodnia |
| X | Komunikacja między użytkownikami z możliwością wyłączenia |
| X | Grupy użytkowników |
| X | Zgłaszanie spamu |
| X | Uprawnienia do nowych funkcji (jak w jokerpila.pl) |

### Inne

| Stan | Zadanie |
|:----:|---------|
| X | PR Dependabota z aktualizacją akcji GitHuba ([MolaQ/LT20#6](https://github.com/MolaQ/LT20/pull/6)) |
