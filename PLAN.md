# Plan poprawek #LechTYPER

V = zrobione, X = do zrobienia. Lista rośnie w trakcie pracy.

### Wdrożenie na lechtyper.pl

| Stan | Zadanie |
|:----:|---------|
| V | Narzędzia do wgrywania bez SSH (`narzedzia/paczka-na-serwer.bat`, cron `instaluj-na-serwerze.sh`) |
| V | Pierwsza instalacja na serwerze (baza MariaDB 10.11 na porcie 3308, migracje, dane startowe) |
| X | Błąd 500 „Unable to find component”: paczka bez ⚡ w nazwach plików (poprawka gotowa, czeka na wgranie i potwierdzenie) |
| X | Scalić poprawkę ⚡ do `master` |

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
| V | Ustawienia konta po samym zalogowaniu; niepotwierdzony e-mail potwierdza się kodem w pasku w ustawieniach (bez wypadania poza stronę) |
| V | Strona „Potwierdź e-mail” w zwykłym układzie strony |
| V | Bez potwierdzonego maila nie można typować (bez zmian, dodany test) |
| X | `dashboard/office`: filtry jako klikalne przyciski |
| X | Filtry jako przyciski zamiast list rozwijanych wszędzie, gdzie się da |

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
| X | Spokojniejsze czcionki bezszeryfowe (dziś Barlow Condensed, wersaliki, bardzo gruba waga) |
| X | Całość pod dorosłych odbiorców (25–55 lat): stonowane kolory, mniej efektów |
| X | Każdy element strony konfigurowalny (w panelu: widoczność, kolejność, teksty sekcji) |

### Do zaplanowania i dyskusji

| Stan | Zadanie |
|:----:|---------|
| X | Statystyki odwiedzin: online, aktualnie na stronie, dzienne odwiedziny (jak w jokerpila.pl) |
| X | Komunikacja między użytkownikami z możliwością wyłączenia |
| X | Grupy użytkowników |
| X | Zgłaszanie spamu |
| X | Uprawnienia do nowych funkcji (jak w jokerpila.pl) |

### Inne

| Stan | Zadanie |
|:----:|---------|
| X | PR Dependabota z aktualizacją akcji GitHuba ([MolaQ/LT20#6](https://github.com/MolaQ/LT20/pull/6)) |
