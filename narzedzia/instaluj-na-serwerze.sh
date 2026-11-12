#!/bin/bash
# Instalacja i aktualizacja strony na hostingu bez SSH (cyber_Folks, DirectAdmin).
# Uruchamia go jedno zadanie cron w panelu, co minutę (* * * * *):
#   bash /home/UZYTKOWNIK/domains/DOMENA/typer/narzedzia/instaluj-na-serwerze.sh
# Co minutę, bo ten sam cron obsługuje kolejkę zadań (przeliczenie kolejki z paskiem postępu) i powiadomienia push.
# Instaluje tylko wtedy, gdy wgrano nową paczkę albo zmieniono .env (nowsze niż ostatnia instalacja);
# w pozostałych uruchomieniach wykonuje tylko zadania okresowe Laravela (schedule:run).
# Przebieg instalacji zapisuje w storage/logs/instalacja.log (podgląd w menedżerze plików i w Panel → Aktualizacja strony).
# Paczkę wgraną w panelu (storage/app/aktualizacja) instaluje tylko po zatwierdzeniu hasłem (plik flaga.json z sumą SHA-256).

# Aktualizacja z panelu (Panel → Aktualizacja strony) podmienia też ten skrypt, a bash czyta plik w trakcie pracy:
# gdy czeka flaga, skrypt uruchamia się z własnej kopii (TYPER_APP wskazuje wtedy folder strony) i na koniec ją usuwa.
if [ -n "$TYPER_APP" ]; then
    cd "$TYPER_APP" || exit 1
    trap 'rm -f "$0"' EXIT
else
    cd "$(dirname "$0")/.." || exit 1
fi
APP="$(pwd)"
AKT="$APP/storage/app/aktualizacja"
if [ -z "$TYPER_APP" ] && [ -f "$AKT/flaga.json" ]; then
    KOPIA_SKRYPTU="$APP/storage/framework/instalator-$$.sh"
    if cp "$0" "$KOPIA_SKRYPTU" 2>/dev/null; then
        TYPER_APP="$APP" exec bash "$KOPIA_SKRYPTU"
    fi
fi
DOMENA="$(dirname "$APP")"
ZNACZNIK="$APP/storage/framework/instalacja-zrobiona"
# Dane startowe (role, admin, boty, kadra Lecha, ikony trofeów, newsy) wpisuje się raz, przy pierwszej instalacji.
DANE_STARTOWE="$APP/storage/framework/dane-startowe-zrobione"
# Numer paczki zapisany przez paczka-na-serwer.bat: inny niż przy ostatniej instalacji = nowa paczka,
# nawet gdy menedżer plików zostawi przy rozpakowaniu stare daty plików.
WERSJA="$APP/bootstrap/wersja-paczki.txt"
LOG="$APP/storage/logs/instalacja.log"

# Paczka rozpakowana o poziom za głęboko (np. typer/typer, typer/typer-kod): przenosi pliki na właściwe miejsce.
for ZAGLEBIONY in "$APP"/*/; do
    ZAGLEBIONY="${ZAGLEBIONY%/}"
    [ -f "$ZAGLEBIONY/artisan" ] && [ -d "$ZAGLEBIONY/vendor" ] || continue
    cp -a "$ZAGLEBIONY/." "$APP/" && rm -rf "$ZAGLEBIONY"
    mkdir -p "$APP/storage/logs"
    echo "$(date '+%Y-%m-%d %H:%M:%S') Paczka była rozpakowana w $ZAGLEBIONY - przeniesiono pliki do $APP." >>"$APP/storage/logs/instalacja.log"
done

# Paczka rozpakowana z uprawnieniami 777 (zip z Windows) daje błąd 403/500: serwer nie uruchamia plików,
# które może zmieniać każdy. Foldery 755, pliki 644 (skrypty .sh zostają wykonywalne).
if [ -n "$(find "$APP" -maxdepth 2 -perm -o+w ! -type l -print -quit 2>/dev/null)" ]; then
    find "$APP" ! -type l -type d -exec chmod 755 {} + 2>/dev/null
    find "$APP" ! -type l -type f -exec chmod 644 {} + 2>/dev/null
    find "$APP/narzedzia" -name '*.sh' -exec chmod 755 {} + 2>/dev/null
    echo "$(date '+%Y-%m-%d %H:%M:%S') Poprawiono uprawnienia plików (foldery 755, pliki 644)." >>"$APP/storage/logs/instalacja.log"
fi

# Widoki z popsutymi nazwami (błyskawica z nazw komponentów Livewire po rozpakowaniu starej paczki).
# Paczka ma te pliki już bez błyskawicy, więc w resources/views nie powinno być nazw spoza ASCII.
if [ -n "$(LC_ALL=C find "$APP/resources/views" -type f -name '*[! -~]*' -print -quit 2>/dev/null)" ]; then
    LC_ALL=C find "$APP/resources/views" -type f -name '*[! -~]*' -delete
    echo "$(date '+%Y-%m-%d %H:%M:%S') Usunięto widoki z nazwami spoza ASCII (stare pliki z błyskawicą)." >>"$APP/storage/logs/instalacja.log"
fi

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache

# PHP 8.3 lub nowszy (Laravel 13): domyślne polecenie php bywa starsze niż wersja wybrana dla strony w panelu.
PHPBIN=""
for P in "$PHP" /usr/local/bin/php85 /usr/local/bin/php84 /usr/local/bin/php83 /usr/local/bin/php /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php \
         /usr/local/php85/bin/php /usr/local/php84/bin/php /usr/local/php83/bin/php php; do
    [ -n "$P" ] && command -v "$P" >/dev/null 2>&1 || continue
    if "$P" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
        PHPBIN="$P"
        break
    fi
done
if [ -z "$PHPBIN" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') BŁĄD: nie znalazłem PHP 8.3 lub nowszego. Ustaw wersję PHP w panelu i wklej ten log Claude." >>"$LOG"
    exit 1
fi

# Aktualizacja wgrana przez panel (Panel → Aktualizacja strony). Rusza tylko z plikiem flagą, który admin
# zatwierdził hasłem; flaga niesie sumę SHA-256 paczki, więc niepełna albo podmieniona paczka nie zostanie rozpakowana.
# Po podmianie plików zmienia się numer w bootstrap/wersja-paczki.txt, więc dalszą instalację (migracje, optimize)
# robi zwykła część skryptu poniżej.
stan_aktualizacji() {
    printf '{"stan":"%s","opis":"%s","czas":"%s"}\n' "$1" "$2" "$(date '+%Y-%m-%d %H:%M:%S')" >"$AKT/stan.json"
}
if [ -f "$AKT/flaga.json" ]; then
    # Blokada po przerwanej instalacji (starsza niż 30 minut) nie może zatrzymać aktualizacji na zawsze.
    if [ -d "$AKT/blokada" ] && [ -n "$(find "$AKT/blokada" -maxdepth 0 -mmin +30 2>/dev/null)" ]; then
        rmdir "$AKT/blokada" 2>/dev/null
    fi
    if ! mkdir "$AKT/blokada" 2>/dev/null; then
        exit 0
    fi
    {
        echo
        echo "=== $(date '+%Y-%m-%d %H:%M:%S') aktualizacja z panelu ==="
    } >>"$LOG"
    KOPIA_KODU="$DOMENA/kopie-typer/kod-poprzedni.tar.gz"
    rm -rf "$AKT/nowa"
    if grep -q '"typ":"cofnij"' "$AKT/flaga.json"; then
        if [ -f "$KOPIA_KODU" ]; then
            "$PHPBIN" artisan down --retry=60 --refresh=30 >>"$LOG" 2>&1
            if tar -xzf "$KOPIA_KODU" -C "$APP" >>"$LOG" 2>&1; then
                echo "Przywrócono poprzednią wersję kodu z $KOPIA_KODU." >>"$LOG"
                rm -f "$ZNACZNIK"
                stan_aktualizacji instalacja "Przywrócono poprzednią wersję, trwa instalacja"
            else
                echo "BŁĄD: nie udało się rozpakować kopii poprzedniej wersji." >>"$LOG"
                stan_aktualizacji blad "Nie udało się przywrócić poprzedniej wersji"
                "$PHPBIN" artisan up >>"$LOG" 2>&1
            fi
        else
            echo "BŁĄD: brak kopii poprzedniej wersji ($KOPIA_KODU)." >>"$LOG"
            stan_aktualizacji blad "Brak kopii poprzedniej wersji"
        fi
    elif "$PHPBIN" narzedzia/rozpakuj-paczke.php "$AKT" >>"$LOG" 2>&1 \
        && [ -f "$AKT/nowa/artisan" ] && [ -f "$AKT/nowa/public/index.php" ] && [ -f "$AKT/nowa/vendor/autoload.php" ]; then
        # Poprzednia wersja kodu zostaje jako jedna kopia do cofnięcia (bez wgranych plików, logów i .env).
        mkdir -p "$DOMENA/kopie-typer"
        if tar -czf "$KOPIA_KODU.nowa" -C "$APP" --exclude=./storage --exclude=./node_modules --exclude=./public/storage --exclude=./.env . 2>>"$LOG"; then
            mv -f "$KOPIA_KODU.nowa" "$KOPIA_KODU"
        else
            rm -f "$KOPIA_KODU.nowa"
            echo "UWAGA: nie udało się zachować poprzedniej wersji kodu (brak miejsca?). Aktualizacja idzie dalej." >>"$LOG"
        fi
        "$PHPBIN" artisan down --retry=60 --refresh=30 >>"$LOG" 2>&1
        if cp -a "$AKT/nowa/." "$APP/" 2>>"$LOG"; then
            echo "Pliki nowej paczki są na miejscu." >>"$LOG"
            rm -f "$ZNACZNIK"
            stan_aktualizacji instalacja "Pliki podmienione, trwa instalacja"
            rm -f "$AKT/typer-kod.zip" "$AKT/paczka.json"
        else
            echo "BŁĄD: kopiowanie plików nowej paczki przerwane." >>"$LOG"
            stan_aktualizacji blad "Kopiowanie plików przerwane, szczegóły w logu"
            rm -f "$ZNACZNIK"
        fi
    else
        echo "Aktualizacja z panelu odrzucona: nic nie zostało zmienione." >>"$LOG"
        stan_aktualizacji blad "Paczka odrzucona, szczegóły w logu"
    fi
    rm -rf "$AKT/nowa"
    rm -f "$AKT/flaga.json"
    rmdir "$AKT/blokada" 2>/dev/null
fi

# Strona zainstalowana i nic nowego nie wgrano: tylko zadania okresowe (kolejka zadań, push, wygasanie premium).
if [ -f "$ZNACZNIK" ] && [ "$ZNACZNIK" -nt vendor/autoload.php ] && [ "$ZNACZNIK" -nt .env ] \
    && { [ ! -f "$WERSJA" ] || cmp -s "$WERSJA" "$ZNACZNIK"; }; then
    "$PHPBIN" artisan schedule:run --no-interaction >/dev/null 2>&1
    exit 0
fi

exec >>"$LOG" 2>&1
echo
echo "=== $(date '+%Y-%m-%d %H:%M:%S') instalacja w $APP ==="

echo "PHP: $PHPBIN ($("$PHPBIN" -r 'echo PHP_VERSION;'))"

if [ ! -f .env ]; then
    echo "BŁĄD: brak pliku .env. Skopiuj narzedzia/env-serwer.txt jako .env w folderze $APP i uzupełnij dane bazy."
    [ -f "$AKT/stan.json" ] && grep -q '"stan":"instalacja"' "$AKT/stan.json" && stan_aktualizacji blad "Brak pliku .env, szczegóły w logu"
    exit 1
fi
if grep -qE '^(DB_[A-Z]*|APP_URL)=.*UZUPELNIJ' .env; then
    echo "BŁĄD: w pliku .env są jeszcze linie DB_ albo APP_URL z UZUPELNIJ. Wpisz dane bazy z panelu i adres strony."
    exit 1
fi
grep -q 'UZUPELNIJ' .env && echo "Uwaga: w .env zostały linie z UZUPELNIJ (np. poczta) - strona działa, ale maile nie wyjdą."

# Folder strony: public_html wskazuje na folder public aplikacji (stary folder zostaje jako kopia).
# Tylko w folderze domeny DirectAdmin (obok jest public_html); private_html bywa już dowiązaniem do public_html.
for KATALOG in public_html private_html; do
    CEL="$DOMENA/$KATALOG"
    if [ -L "$CEL" ] || [ ! -d "$CEL" ]; then
        continue
    fi
    mv "$CEL" "${CEL}_stary_$(date +%Y%m%d%H%M%S)"
    ln -s "$(basename "$APP")/public" "$CEL"
    echo "$KATALOG wskazuje teraz na $(basename "$APP")/public"
done

# Po rozpakowaniu w menedżerze plików foldery bywają 777; serwer (suPHP/LiteSpeed) odmawia wtedy uruchomienia strony (błąd 403/500).
find "$APP" -path "$APP/storage/app/public" -prune -o -type d ! -perm 755 -exec chmod 755 {} +
find "$APP" -path "$APP/storage/app/public" -prune -o -type f ! -perm 644 -exec chmod 644 {} +

# Pliki z paczki mają datę z 1980 roku: OPcache serwera uznałby zmieniony plik PHP za ten sam
# i dalej uruchamiał starą wersję. Świeża data wymusza wczytanie nowego kodu.
find "$APP" \( -path "$APP/storage" -o -path "$APP/node_modules" \) -prune -o -type f -name '*.php' -exec touch {} +

BLAD=0
krok() {
    echo "--- $*"
    "$PHPBIN" artisan "$@" --no-interaction || { echo "BŁĄD w kroku: $*"; BLAD=1; return 1; }
}

grep -q '^APP_KEY=base64:' .env || krok key:generate --force
# Klucze powiadomień push: bez nich powiadomienia są wyłączone. Raz wygenerowanych nie zmieniamy,
# bo nowe klucze unieważniłyby zapisane subskrypcje telefonów.
if ! grep -q '^VAPID_PUBLIC_KEY=.' .env && "$PHPBIN" artisan list --raw 2>/dev/null | grep -q '^webpush:vapid '; then
    krok webpush:vapid --force
fi
# Najpierw tylko zapamiętana konfiguracja (dane bazy z nowego .env), pełne czyszczenie po migracjach,
# bo potrzebuje tabeli cache w bazie.
krok config:clear
krok migrate --force
if [ ! -f "$DANE_STARTOWE" ]; then
    krok db:seed --force && touch "$DANE_STARTOWE" \
        && echo "Dane startowe wpisane. Admin: konto z CreateAdminUserSeeder, hasło: password - ZMIEŃ JE od razu po zalogowaniu."
fi
krok optimize:clear
[ -L public/storage ] || krok storage:link
krok optimize
# Worker kolejki z poprzedniej paczki kończy pracę i przy następnej minucie startuje już z nowym kodem.
krok queue:restart

# Po aktualizacji z panelu strona stoi na planszy przerwy technicznej: zdejmujemy ją niezależnie od wyniku.
if [ -f storage/framework/down ]; then
    "$PHPBIN" artisan up --no-interaction
fi
if [ -f "$AKT/stan.json" ] && grep -q '"stan":"instalacja"' "$AKT/stan.json"; then
    if [ "$BLAD" -eq 0 ]; then
        stan_aktualizacji gotowe "Aktualizacja zainstalowana"
    else
        stan_aktualizacji blad "Instalacja zakończona z błędami, szczegóły w logu"
    fi
fi

if [ "$BLAD" -eq 0 ]; then
    if [ -f "$WERSJA" ]; then cp "$WERSJA" "$ZNACZNIK"; fi
    touch "$ZNACZNIK"
    echo "Paczka: $(cat "$WERSJA" 2>/dev/null || echo bez numeru)"
    echo "=== GOTOWE ==="
else
    echo "=== Były błędy (powyżej). Wklej ten log Claude. Kolejna próba przy następnym uruchomieniu crona. ==="
fi
