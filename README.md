sportsdoc
=================

composer create-project symfony/skeleton:"^4.4" sportsdoc

composer require symfony/maker-bundle

composer require symfony/orm-pack

composer require twig

composer require knpuniversity/oauth2-client-bundle

composer require league/oauth2-google

composer require league/oauth2-facebook

php bin/console doctrine:database:create

php bin/console doctrine:schema:validate

php bin/console make:entity

Place

php bin/console doctrine:schema:validate

php bin/console make:migration

php bin/console doctrine:migrations:migrate

php bin/console doctrine:schema:validate

php bin/console make:crud

composer require form validator security-csrf annotations

php bin/console make:crud

php -S 127.0.0.1:9046

php bin/console make:user

composer require security

php bin/console make:migration

php bin/console doctrine:migrations:migrate

php bin/console doctrine:schema:validate

php bin/console make:crud

php -S 127.0.0.1:9046

composer require symfony/asset

git config --global user.email "logic@op.pl"
git config --global user.name "abedo"

git commit -m "first commit"

git branch -M master

git remote add origin https://github.com/abedo/sportsdoc.git

git push -u origin master

Resources
https://dev.to/_mertsimsek/integrate-oauth2-for-symfony-4-360c
