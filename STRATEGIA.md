# #LechTYPER – plan rozwoju i monetyzacji

Założenia: gra ma zabierać **kilka minut na kolejkę**, dawać **radość i emocje meczu**, pomagać **poznawać innych kibiców**,
a głównym kanałem jest **X** (tam się zaczęło), potem kolejne miejsca. Każda propozycja ma ocenę:
**nakład** (S – dni, M – tydzień–dwa, L – miesiąc+) i **wpływ** na przychód (€) albo zaangażowanie (♥).

---

## 1. Co już jest (punkt wyjścia)

| Obszar | Stan |
|---|---|
| Rdzeń gry | typ wyniku, pytania tak/nie, zawodnicy, ligi po 10, puchar, Liga Legend, puchary europejskie, Hall of Fame |
| Zaangażowanie | rywale w kolejce, powiadomienia push, ostrzeżenie „kartki”, statystyki premium, profile klubów z polubieniami |
| Udostępnianie | obrazek na sociale z każdej tabeli i kolejki, „Opublikuj na X”, podgląd linków na X z obrazkiem |
| Przychód | premium (5–100 zł), cegiełki, Złota Liga z wpłat, sponsorzy sezonu i rozgrywek (logo, link) |
| Dane | licznik odwiedzin, statystyki odwiedzin w panelu |

To dużo więcej niż mają typowe typery kibicowskie. Brakuje głównie **pętli społecznej** (znajomi, rozmowy, grupy)
i **powtarzalnego wzrostu z X**.

---

## 2. Jak działają dziś najlepsze gry tego typu (wzorce)

| Wzorzec | Przykłady | Co z tego bierzemy |
|---|---|---|
| **Typowanie w 30 sekund** | Superbru, Predictor (Sky Bet Super 6) | jeden ekran, domyślny typ, przypomnienie push 2 h przed meczem |
| **Prywatne ligi znajomych** | Fantasy Premier League, Superbru | najważniejsza funkcja społeczna: kod zaproszenia, własna tabela, czat |
| **Serie i odznaki** | Duolingo, FPL „chips” | serie trafień, odznaki sezonu, „ochrona serii” jako korzyść premium |
| **Podsumowania do udostępnienia** | Spotify Wrapped, Strava | tygodniowa i sezonowa karta gracza gotowa na X |
| **Rywalizacja 1 na 1** | Sorare, Kicktipp | pojedynki z rywalem z ligi (już są) + wyzwanie znajomego |
| **Gra w czasie meczu** | aplikacje klubowe, Twitch Predictions | szybkie ankiety: MVP meczu, gol do przerwy (bez punktów do tabeli, dla zabawy) |
| **Wejście jednym kliknięciem** | gry na X, Discord | logowanie przez X albo Google: mniej porzuceń przy rejestracji |

---

## 3. Monetyzacja – od najszybszej do największej

### 3.1 Premium jako „karnet sezonowy” (€, nakład S)
- Zamiast pięciu planów: **karnet na sezon** (np. 29 zł) + **miesiąc** (np. 7 zł). Prosto jak karnet na stadion.
- Wartość premium bez „płacenia za wygrywanie”:
  - statystyki, podgląd rywali, brak reklam, kolory i ramki profilu klubu, odznaka „Kibic wspierający”,
  - zakładanie **prywatnych lig** (zwykły gracz: dołącza do dowolnej, zakłada 1),
  - **ochrona przed statusem Inactive** (już jest).
- ⚠️ **Domyślny typ dla premium** daje przewagę w punktacji. Dopóki nie ma nagród rzeczowych – w porządku.
  Jeśli pojawią się nagrody, wyłączyć go z punktacji o nagrody (patrz 6. Prawo).
- **Polecenia:** „zaproś znajomego – obaj dostajecie 7 dni premium”. Najtańszy wzrost liczby graczy.

### 3.2 Sponsoring – największy realny przychód na start (€€, nakład M)
- **Prawa do nazwy:** „Ekstraklasa #LechTYPER powered by [firma]”, puchar, Liga Legend, Złota Liga.
- **Sponsor kolejki:** baner na stronie kolejki, wzmianka w powiadomieniu i na obrazku na X.
- **Nagrody sponsorów:** koszulka dla mistrza ligi, voucher do pubu dla zwycięzcy kolejki, bilety na mecz.
- Dla lokalnych firm (puby, sklepy sportowe, kebaby przy stadionie, firmy kibiców) to tania i celna reklama.
- **Strona „Zostań sponsorem”** z pakietami, cenami i liczbami z panelu statystyk (odwiedziny, gracze, zasięg na X).
- Przykładowe pakiety (do ustalenia): kolejka 50–150 zł, liga na sezon 300–800 zł, cały sezon 1500–3000 zł.

### 3.3 Reklama (€, nakład S–M)
- **Najpierw własne reklamy** sprzedawane sponsorom (lepsza stawka, pełna kontrola), miejsca: strona główna,
  wyniki, stopka. **Bez reklam dla premium** – to dodatkowa zachęta do premium.
- Sieć (np. Google AdSense) dopiero przy dużym ruchu – przy kilku tysiącach graczy daje grosze.

### 3.4 Monetyzacja konta na X (€, nakład S)
- **Subskrypcje na X** (Creator Subscriptions): materiały „za kulisami”, wcześniejsze tabele, AMA z adminem.
- Udział w przychodach z reklam X przy dużych zasięgach postów.
- Wspólne posty ze sponsorami (sponsor kolejki w poście z wynikami).

### 3.5 Wsparcie społeczności (€, już jest)
- Cegiełki i Złota Liga zostają. Dodać cel na sezon („serwer i nagrody: 1200 zł – zebrano 60%”) – pasek celu działa lepiej niż sam przycisk.

### 3.6 Platforma dla innych klubów – największy potencjał (€€€, nakład L)
- Kod jest już przygotowany pod „jedna instalacja = jeden klub” (`config/club.php`).
- **Licencja dla społeczności innych klubów** (Ekstraklasa, 1. liga, kluby za granicą):
  abonament miesięczny za gotową grę z ich barwami, logo i kalendarzem.
- Model: abonament za instalację albo udział w przychodach z premium i sponsorów.

---

## 4. Funkcje dające radość i znajomości (bez zabierania czasu)

| # | Funkcja | Po co | Nakład |
|---|---|---|---|
| 1 | **Prywatne ligi znajomych** (kod, tabela, czat grupy) | najsilniejsza pętla społeczna; zapraszanie ludzi z X | M |
| 2 | **Wiadomości 1:1 i reakcje** pod wynikami kolejki (emoji, „gratulacje”) | znajomości bez długiego pisania | M |
| 3 | **Tygodniowa karta gracza** („mój tydzień: 3 pkt, 2 miejsce w lidze”) gotowa na X | darmowy zasięg na X | S |
| 4 | **Odznaki i serie** (trafione z rzędu, dokładny wynik, „wierny kibic”) | powód, by wracać | S |
| 5 | **Ankiety w czasie meczu** (MVP, gol do przerwy) – dla zabawy | emocje meczu w aplikacji | M |
| 6 | **Wyzwanie znajomego** (pojedynek na kolejkę, link do wysłania na X) | wirusowe zaproszenia | S |
| 7 | **Logowanie przez X** (i Google) | szybsza rejestracja odbiorców z X | S |
| 8 | **Przerwa między sezonami:** typowanie transferów, ranking wszech czasów, głosowania | aktywność, gdy nie ma meczów | M |
| 9 | **Kibic tygodnia** wybierany przez graczy | wyróżnienie i rozpoznawalność w społeczności | S |

---

## 5. X jako główny kanał – plan

**Już działa:** podgląd linków z obrazkiem, obrazki z tabel i kolejek, „Opublikuj na X”.

| Krok | Co | Nakład |
|---|---|---|
| 1 | **Generator materiałów w panelu** („Materiały na X”): jednym kliknięciem obrazki: wyniki kolejki, top 10, dokładne typy, niespodzianka kolejki, tabela ligi, mistrz sezonu | M |
| 2 | **Stały rytm postów:** otwarcie typowania (−3 dni), przypomnienie (−2 h), wyniki (po meczu), podsumowanie tygodnia | S (praca redakcyjna) |
| 3 | **Karty podglądu generowane dla kolejek i tabel** (link do kolejki = obrazek z wynikami w poście) | M |
| 4 | **Hashtag i oznaczanie:** #LechTYPER + konto w każdym poście z gry; gracze oznaczają się w kartach | S |
| 5 | **X Spaces po meczu** z najlepszymi typerami kolejki | S |
| 6 | **Automatyczne posty przez API X** – później: płatny dostęp do API X kosztuje miesięcznie, więc najpierw ręcznie z generatora | L |

Kolejne kanały po X: **Facebook (grupy kibiców)**, **Instagram (karty jako stories)**, **WhatsApp (kanał z przypomnieniami)**, **TikTok** (krótkie podsumowania).

---

## 6. Prawo i ryzyka (do konsultacji z prawnikiem przed nagrodami)

- **Gra musi zostać darmowa przy nagrodach.** Odpłatny udział + nagrody pieniężne lub rzeczowe zależne od wyniku
  może zostać uznany za zakład wzajemny w rozumieniu ustawy o grach hazardowych.
  Bezpiecznie: udział za darmo, nagrody od sponsorów, premium bez wpływu na szanse w rywalizacji o nagrody.
- **Reklama bukmacherów:** obwarowana przepisami (tylko legalni operatorzy, ograniczenia treści) – na start odradzam.
- **RODO:** statystyki już są bez IP i ciasteczek; przy wiadomościach i grupach potrzebny regulamin i zgłaszanie treści.
- **Znaki klubu:** nazwa i herb Lecha Poznań – gra jest nieoficjalna (napis w stopce); przy sponsoringu unikać sugerowania współpracy z klubem.
- **Faktury i podatki** przy sponsoringu i premium: działalność albo fundacja/stowarzyszenie kibiców – do decyzji.

---

## 7. Proponowana kolejność

| Etap | Co | Efekt |
|---|---|---|
| **1. Teraz (1–2 tyg.)** | wdrożenie na serwer, Przelewy24 na produkcji, karnet sezonowy, polecenia, strona „Zostań sponsorem” | pierwszy przychód |
| **2. Społeczność (3–6 tyg.)** | zgłaszanie spamu, wiadomości, prywatne ligi znajomych, logowanie przez X, tygodniowa karta gracza | wzrost graczy z X |
| **3. Treści na X (równolegle)** | generator materiałów w panelu, rytm postów, karty podglądu kolejek | stały zasięg |
| **4. Emocje meczu (sezon)** | ankiety w czasie meczu, odznaki i serie, kibic tygodnia | powroty co kolejkę |
| **5. Skala (później)** | panel sponsorów z raportami, platforma dla innych klubów | duży przychód |

## 8. Jak mierzyć

| Wskaźnik | Cel na start |
|---|---|
| Gracze typujący co kolejkę | > 70% listy sezonu |
| Powrót po tygodniu (nowi gracze) | > 40% |
| Konwersja na premium | 3–5% aktywnych |
| Wejścia z X (statystyki odwiedzin → źródła) | rosnące co miesiąc |
| Sponsorzy na sezon | 3–5 lokalnych firm |

Przykład: przy ~1450 graczach i 4% premium po 29 zł za sezon to ok. 1700 zł na sezon z premium,
a 3 sponsorów lig i 1 sponsor sezonu to drugie tyle albo więcej. Licencja dla innych klubów może to wielokrotnie przebić.
