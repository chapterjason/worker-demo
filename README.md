
# Deployment

```
/opt/plesk/php/8.2/bin/php /opt/psa/var/modules/composer/composer.phar install --no-interaction --no-dev --optimize-autoloader
/opt/plesk/php/8.2/bin/php bin/console cache:clear --no-interaction --no-warmup --env=prod
/opt/plesk/php/8.2/bin/php bin/console cache:warmup --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console assets:install --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console doctrine:migrations:migrate --allow-no-migration --all-or-nothing --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console messenger:stop-workers --env=prod
```