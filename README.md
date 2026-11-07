# Sportsdoc

Ewidencja uczestnictwa w zajęciach sportowych: miejsca, gry, lista obecności, wpłaty i rozliczenia. Symfony 8.1, PHP 8.4+, Doctrine ORM 3, logowanie przez Google i Facebooka (KnpU OAuth2).

Projekt przeniesiony z Symfony 4.4 (2020). Szczegóły w sekcji „Co się zmieniło”.

## Jak to działa

Zapisy na zajęcia odbywają się poza aplikacją (np. na grupach na Facebooku). Sportsdoc służy do obecności i rozliczeń. Facebook nie udostępnia API grup, więc nic nie jest stamtąd importowane.

**Role.** Są dwie: organizator i uczestnik. Przy pierwszym logowaniu osoba bez roli widzi stronę z dwoma przyciskami („Jestem organizatorem”, „Jestem uczestnikiem”). Rolę można zmienić w „Moim rozliczeniu”. Po wyborze logowanie prowadzi organizatora na miejsca, a uczestnika na gry. W menu obok nazwy widać etykietę roli. Krótką instrukcję pokazuje publiczna strona `/help`.
- **Organizator** zakłada miejsca i od razu zostaje organizatorem tego miejsca. W swoim miejscu dodaje gry, zaznacza obecność wszystkich, potwierdza wpłaty, mianuje innych organizatorów i usuwa uczestników. W cudzych miejscach nie ma uprawnień. Zawsze zostaje co najmniej jeden organizator miejsca.
- **Uczestnik** wyszukuje miejsce („Znajdź miejsce”: po nazwie lub mieście) i dołącza jednym kliknięciem. Zaznacza „Byłem / Nie byłem” tylko dla siebie i zgłasza wpłatę. Zgłoszona wpłata czeka na potwierdzenie organizatora tego miejsca i do tego czasu nie zmienia salda.

Rolę wybiera każdy sam, nie ma administratora serwisu. Uprawnienia w miejscu wynikają z członkostwa (tabela `place_member`), a globalna rola decyduje tylko o tym, kto może zakładać miejsca.

Ekran „Miejsca” pokazuje tylko moje miejsca (przełączanie strzałkami, ← → albo przesunięciem palcem). Każde miejsce rozlicza się osobno: saldo = potwierdzone wpłaty minus koszt gier, w których osoba była, w tym miejscu. Opuszczenie miejsca nie kasuje historii.

**Logowanie.** Google i Facebook. Osobę rozpoznajemy po stałym identyfikatorze u dostawcy (tabela `user_identity`), a e-mail jest opcjonalny, bo Facebook go nie zawsze zwraca. Google dopina się do istniejącej osoby z tym samym zweryfikowanym e-mailem. Facebook nigdy nie łączy po e-mailu (ryzyko przejęcia konta).

## Uruchomienie lokalnie

```
composer install
cp .env .env.local        # ustaw w .env.local: DATABASE_URL, GOOGLE_CLIENT_ID/SECRET, FACEBOOK_APP_ID/SECRET
php bin/console doctrine:migrations:migrate
symfony server:start      # albo: php -S 127.0.0.1:8000 -t public
```

Bez serwera MySQL można użyć SQLite: w `.env.local` ustaw
`DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"` i zamiast migracji uruchom
`php bin/console doctrine:schema:create` (stare migracje zawierają SQL specyficzny dla MySQL).

Lokalnie bez Google: w trybie dev strona `/dev/login` pozwala zalogować się jako dowolna osoba z bazy (nie istnieje w prod ani w testach).

Logowanie Google: w Google Cloud Console dodaj adres zwrotny `https://TWOJA-DOMENA/connect/google/check`.

Logowanie Facebook: w developers.facebook.com utwórz aplikację z produktem „Facebook Login” i dodaj ważny adres przekierowania `https://TWOJA-DOMENA/connect/facebook/check`. Dopóki aplikacja jest w trybie deweloperskim, logować mogą się tylko osoby dodane jako testerzy. Żeby otworzyć ją dla wszystkich, przełącz aplikację na tryb publiczny, a przed tym podaj adres polityki prywatności i adres usuwania danych użytkownika. Wystarczą uprawnienia `public_profile` i `email` (nie wymagają pełnej weryfikacji aplikacji).

## Testy

```
php bin/phpunit
```

Testy funkcjonalne (CRUD wszystkich encji, strona logowania, przekierowanie do Google) działają na SQLite w pliku `var/test.db`, więc nie potrzebują MySQL.

## Co się zmieniło względem Symfony 4.4

- Adnotacje Doctrine i `@Route` zastąpione atrybutami PHP, encje mają typowane właściwości.
- Usunięte `sensio/framework-extra-bundle`, `composer/package-versions-deprecated` oraz niewykorzystywany `UserProvider`.
- Logowanie: stary Guard authenticator zastąpiony nowym systemem (`SocialAuthenticator` na `OAuth2Authenticator`, `custom_authenticators`). `User` implementuje `getUserIdentifier()` (id osoby).
- Kontrolery: `getDoctrine()` zastąpione wstrzykiwanym `EntityManagerInterface`. Dodano `/` (przekierowanie do listy miejsc) oraz `/logout`. Poprzednie przekierowania po logowaniu wskazywały nieistniejące trasy (`default`, `blog_index`).
- Schemat bazy jest równoważny z dotychczasowym (te same tabele, kolumny, indeksy i klucze), więc istniejąca baza nie wymaga zmian.
- Pominięty EasyAdmin: w projekcie był podpięty w routingu, ale bez żadnej skonfigurowanej encji.

## Wdrożenie na iqhost

`deploy/iqhost.sh` buduje paczkę bez zależności dev i testów, wgrywa ją przez rsync (klucz `~/.ssh/iqhost_ed25519`, konto `host32414`, host `host32414.iqhs.pl`) do `~/domains/abedo.pl/public_html/sportsdoc` i ogrzewa cache PHP 8.4. Główny `.htaccess` kieruje ruch do `public/`. Skrypt nie nadpisuje `var/` ani `.env.local`.

**PHP:** Symfony 8 wymaga 8.4, a panel DirectAdmin oferuje dla tej domeny tylko 8.3 i 7.4. Wersję 8.4 wymusza linia `AddHandler application/x-httpd-php84 php` w `.htaccess` (w głównym i w `public/`). Nie usuwaj jej. CLI na serwerze: `/opt/alt/php84/usr/bin/php`.

**Pierwsze uruchomienie** (jednorazowo, na serwerze w `.env.local`, który nie trafia do repozytorium):

```
DATABASE_URL="mysql://UZYTKOWNIK:HASLO@localhost:3306/NAZWA_BAZY?serverVersion=8.0.32&charset=utf8mb4"
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
FACEBOOK_APP_ID=...
FACEBOOK_APP_SECRET=...
```

Potem na serwerze: `php bin/console doctrine:migrations:migrate --env=prod`. Pierwsza osoba po zalogowaniu wybiera rolę na stronie powitalnej, więc żadnej komendy nie trzeba. Adresy zwrotne: `https://sportsdoc.abedo.pl/connect/google/check` (Google Cloud Console) i `https://sportsdoc.abedo.pl/connect/facebook/check` (Facebook Login).

## Dostęp i sekrety

- Bez logowania dostępna jest tylko strona logowania. Uprawnienia do miejsc, gier i wpłat sprawdza `PlaceVoter` (`PLACE_VIEW`: członek miejsca, `PLACE_MANAGE`: organizator miejsca). Globalne reguły są w `config/packages/security.yaml`.
- Każde konto, które się zaloguje, jest zwykłą osobą bez dostępu do żadnego miejsca, dopóki do niego nie dołączy. Miejsca są jawne w wyszukiwarce (nazwa i adres), a lista uczestników i obecności jest widoczna tylko dla członków. Jeśli dołączanie ma wymagać zatwierdzenia, trzeba dodać prośby o dołączenie.
- Sekretów (hasło bazy, klucze Google i Facebooka) nie trzymamy w repozytorium: tylko w `.env.local` lub zmiennych środowiska.
