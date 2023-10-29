
# Deployment

```
/opt/plesk/php/8.2/bin/php /opt/psa/var/modules/composer/composer.phar install --no-interaction --no-dev --optimize-autoloader
/opt/plesk/php/8.2/bin/php bin/console cache:clear --no-interaction --no-warmup --env=prod
/opt/plesk/php/8.2/bin/php bin/console cache:warmup --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console assets:install --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console doctrine:migrations:migrate --allow-no-migration --all-or-nothing --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console messenger:stop-workers --env=prod
```

# Some requirements for usage

1. The option `prefix_seed` must be configured in the [`cache.yaml`](config/packages/cache.yaml) file.
2. A failure transport must be configured in the [`messenger.yaml`](config/packages/messenger.yaml) file.
3. The migration must be made with the Schema Class to be compatible with multiple databases. (mysql/postgresql/...)
4. User must be allowed to run services with `loginctl enable-linger <user>`
5. 