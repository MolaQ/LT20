# Do omówienia: odwiedziny, komunikacja, grupy, spam, uprawnienia

Przy każdym pytaniu jest moja propozycja (**➜**). Wystarczy odpisać np. „A1 tak, B2 inaczej: …”.
Wzór z jokerpila.pl: odsłony stron (`page_views`, 400 dni), rozmowy 1:1 i grupowe (`conversations`, `messages`),
zgłaszanie i ukrywanie wiadomości, uprawnienie „pisanie wiadomości” per rola.

## A. Statystyki odwiedzin

| | Pytanie | Propozycja |
|---|---|---|
| A1 | Co liczymy? | ➜ Odsłony stron i unikalnych odwiedzających dziennie (bez IP i bez ciasteczek śledzących, zgodnie z RODO). „Teraz na stronie” już jest w stopce. |
| A2 | Jak długo trzymamy? | ➜ 400 dni (jak w Jokerze), żeby porównywać sezony. |
| A3 | Co widzi admin? | ➜ Strona „Statystyki” w panelu: wykres dni i tygodni, najpopularniejsze strony, wejścia z telefonu i komputera, godziny szczytu (np. przed kolejką). |
| A4 | Co widzą wszyscy? | ➜ W stopce dochodzi „Dziś odwiedzin”. Reszta tylko w panelu. |

## B. Komunikacja między użytkownikami

| | Pytanie | Propozycja |
|---|---|---|
| B1 | Jaka forma? | ➜ Wiadomości prywatne 1:1 i rozmowy grupowe (jak w Jokerze). Bez publicznego czatu na start. |
| B2 | Kto może pisać? | ➜ Gracze z potwierdzonym mailem, bez roli Banned. Boty nie piszą. |
| B3 | Wyłączenie przez gracza | ➜ W Ustawieniach: „Przyjmuj wiadomości: od wszystkich / tylko od rywali i znajomych z grup / od nikogo” oraz blokowanie konkretnej osoby. |
| B4 | Wyłączenie przez admina | ➜ Wyłącznik całej komunikacji w panelu (np. na czas kłopotów) i blokada pisania dla wybranego gracza. |
| B5 | Powiadomienia | ➜ Push „Nowa wiadomość” (już mamy powiadomienia push) i licznik nieprzeczytanych w menu. Mail raz dziennie z podsumowaniem, do wyłączenia. |
| B6 | Komentarze pod newsami? | ➜ Na później, osobna decyzja. |

## C. Grupy

| | Pytanie | Propozycja |
|---|---|---|
| C1 | Czym jest grupa? | ➜ **Prywatna liga znajomych**: własna tabela z typów jej członków (te same punkty co w grze), do tego rozmowa grupy. To najpopularniejsza funkcja w typerach. |
| C2 | Kto zakłada? | ➜ Każdy gracz może dołączyć; zakładać mogą konta premium (zachęta do premium). Do decyzji: czy też zwykli gracze, np. 1 grupa. |
| C3 | Jak dołączyć? | ➜ Link z zaproszeniem albo kod; założyciel akceptuje lub usuwa członków. |
| C4 | Limit członków | ➜ Np. 50 osób w grupie, 5 grup na gracza. |

## D. Zgłaszanie spamu

| | Pytanie | Propozycja |
|---|---|---|
| D1 | Co można zgłosić? | ➜ Wiadomość, nazwę zespołu, później komentarze. Przycisk „Zgłoś” z powodem (spam, obraźliwe, inne). |
| D2 | Gdzie trafia? | ➜ Do Biura skarg i wniosków w panelu, jako nowy rodzaj sprawy „Zgłoszenie”. |
| D3 | Ukrywanie | ➜ Po 3 zgłoszeniach od różnych osób treść chowa się do decyzji admina. Admin ukrywa albo przywraca jednym kliknięciem. |
| D4 | Ochrona przed spamem | ➜ Limit, np. 30 wiadomości na godzinę i 5 nowych rozmów dziennie; nowe konta (do 2 dni) tylko odpowiadają. |

## E. Uprawnienia

| | Pytanie | Propozycja |
|---|---|---|
| E1 | Nowe uprawnienia | ➜ `messages-write`, `groups-create`, `reports-review`, `stats-view` w stronie „Role i uprawnienia”. |
| E2 | Kto co dostaje? | ➜ User i Premium: pisanie wiadomości; Premium: zakładanie grup; Admin: wszystko; Banned i Inactive: nic. |

## Kolejność wdrożenia

| | Etap | Uwagi |
|---|---|---|
| 1 | Statystyki odwiedzin (A) | mały, niezależny |
| 2 | Zgłaszanie i limity (D) | musi być przed komunikacją |
| 3 | Wiadomości z wyłącznikami (B) i uprawnienia (E) | |
| 4 | Grupy / prywatne ligi (C) | korzysta z wiadomości i uprawnień |
