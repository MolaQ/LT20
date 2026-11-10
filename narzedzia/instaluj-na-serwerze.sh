#!/bin/bash
# Instalacja i aktualizacja strony na hostingu bez SSH (cyber_Folks, DirectAdmin).
# Uruchamia go jedno zadanie cron w panelu, co minutę (* * * * *):
#   bash /home/UZYTKOWNIK/domains/DOMENA/typer/narzedzia/instaluj-na-serwerze.sh
# Co minutę, bo ten sam cron obsługuje kolejkę zadań (przeliczenie kolejki z paskiem postępu) i powiadomienia push.
# Instaluje tylko wtedy, gdy wgrano nową paczkę albo zmieniono .env (nowsze niż ostatnia instalacja);
# w pozostałych uruchomieniach wykonuje tylko zadania okresowe Laravela (schedule:run).
# Przebieg instalacji zapisuje w storage/logs/instalacja.log (podgląd w menedżerze plików).

cd "$(dirname "$0")/.." || exit 1
APP="$(pwd)"
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

if [ "$BLAD" -eq 0 ]; then
    if [ -f "$WERSJA" ]; then cp "$WERSJA" "$ZNACZNIK"; fi
    touch "$ZNACZNIK"
    echo "Paczka: $(cat "$WERSJA" 2>/dev/null || echo bez numeru)"
    echo "=== GOTOWE ==="
else
    echo "=== Były błędy (powyżej). Wklej ten log Claude. Kolejna próba przy następnym uruchomieniu crona. ==="
fi
