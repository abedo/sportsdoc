# Sportsdoc

Ewidencja uczestnictwa w zajęciach sportowych: miejsca, gry, lista obecności, wpłaty i rozliczenia. Symfony 8.1, PHP 8.4+, Doctrine ORM 3, logowanie przez Google (KnpU OAuth2).

Projekt przeniesiony z Symfony 4.4 (2020). Szczegóły w sekcji „Co się zmieniło”.

## Jak to działa

**Organizator** (`ROLE_ADMIN`):
1. Na ekranie startowym przełącza miejsca strzałkami (albo ← →, albo przesunięciem palcem).
2. Na karcie miejsca klika „Dodaj grę” i podaje datę. Koszt jest podpowiedziany z poprzedniej gry w tym miejscu.
3. Na stronie edycji gry ustawia koszt i zaznacza, kto był (lista obecności).
4. W „Płatnościach” potwierdza wpłaty zgłoszone przez osoby i zarządza osobami.

**Zwykła osoba** (`ROLE_USER`): przegląda miejsca i gry, w widoku gry zaznacza „Byłem / Nie byłem” tylko dla siebie, a w „Moim rozliczeniu” widzi saldo i zgłasza wpłatę. Zgłoszona wpłata czeka na potwierdzenie organizatora i do tego czasu nie zmienia salda.

Saldo = potwierdzone wpłaty minus koszt gier, w których osoba była.

Nowy organizator: checkbox „Organizator” w formularzu osoby albo komenda
`php bin/console app:user:admin adres@example.com` (z `--remove` odbiera rolę). Pierwszego organizatora
trzeba nadać komendą, bo logowanie przez Google tworzy tylko zwykłe konta.

## Uruchomienie lokalnie

```
composer install
cp .env .env.local        # ustaw w .env.local: DATABASE_URL, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET
php bin/console doctrine:migrations:migrate
symfony server:start      # albo: php -S 127.0.0.1:8000 -t public
```

Bez serwera MySQL można użyć SQLite: w `.env.local` ustaw
`DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"` i zamiast migracji uruchom
`php bin/console doctrine:schema:create` (stare migracje zawierają SQL specyficzny dla MySQL).

Lokalnie bez Google: w trybie dev strona `/dev/login` pozwala zalogować się jako dowolna osoba z bazy (nie istnieje w prod ani w testach).

Logowanie Google: w Google Cloud Console dodaj adres zwrotny `https://TWOJA-DOMENA/connect/google/check`.

## Testy

```
php bin/phpunit
```

Testy funkcjonalne (CRUD wszystkich encji, strona logowania, przekierowanie do Google) działają na SQLite w pliku `var/test.db`, więc nie potrzebują MySQL.

## Co się zmieniło względem Symfony 4.4

- Adnotacje Doctrine i `@Route` zastąpione atrybutami PHP, encje mają typowane właściwości.
- Usunięte `sensio/framework-extra-bundle`, `composer/package-versions-deprecated` oraz niewykorzystywany `UserProvider`.
- Logowanie: stary Guard authenticator zastąpiony nowym systemem (`GoogleAuthenticator` na `OAuth2Authenticator`, `custom_authenticators`). `User` implementuje `getUserIdentifier()`, dostawca użytkowników szuka po `email`.
- Kontrolery: `getDoctrine()` zastąpione wstrzykiwanym `EntityManagerInterface`. Dodano `/` (przekierowanie do listy miejsc) oraz `/logout`. Poprzednie przekierowania po logowaniu wskazywały nieistniejące trasy (`default`, `blog_index`).
- Schemat bazy jest równoważny z dotychczasowym (te same tabele, kolumny, indeksy i klucze), więc istniejąca baza nie wymaga zmian.
- Pominięty EasyAdmin: w projekcie był podpięty w routingu, ale bez żadnej skonfigurowanej encji.

## Dostęp i sekrety

- Bez logowania dostępna jest tylko strona logowania. Uprawnienia organizatora są sprawdzane w `config/packages/security.yaml` (`access_control`) i dodatkowo na kontrolerach (`#[IsGranted]`).
- Każde konto Google, które się zaloguje, dostaje konto z rolą `ROLE_USER`, więc widzi miejsca, gry i listy obecnych. Jeśli lista osób ma być prywatna, trzeba dodać zatwierdzanie nowych kont.
- Sekretów (hasło bazy, klucze Google) nie trzymamy w repozytorium: tylko w `.env.local` lub zmiennych środowiska.
