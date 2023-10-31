
# Deployment

```
/opt/plesk/php/8.2/bin/php /opt/psa/var/modules/composer/composer.phar install --no-interaction --no-dev --optimize-autoloader
/opt/plesk/php/8.2/bin/php bin/console cache:clear --no-interaction --no-warmup --env=prod
/opt/plesk/php/8.2/bin/php bin/console cache:warmup --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console assets:install --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console doctrine:migrations:migrate --allow-no-migration --all-or-nothing --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console worker:reload --no-interaction --env=prod
/opt/plesk/php/8.2/bin/php bin/console messenger:stop-workers --env=prod
```

# Some requirements for usage

1. The option `prefix_seed` must be configured in the [`cache.yaml`](config/packages/cache.yaml) file.
2. A failure transport must be configured in the [`messenger.yaml`](config/packages/messenger.yaml) file.
3. The migration must be made with the Schema Class to be compatible with multiple databases. (mysql/postgresql/...)
4. User must be allowed to run services with `loginctl enable-linger <user>`
5. Generate app secret with `php -r 'echo bin2hex(random_bytes(16));'`
6. FULLY configure the `.env.local.php` before running Composer install in plesk.
7. Allow ssh access in php for domain with `/usr/bin/bash` this provides access to php and other tools for additional deployment steps.
8. Configure under the additional php directives
   ```
   [php-fpm-pool-settings] 
   env[PHP_PATH]="/var/www/vhosts/<HOST>/.phpenv/shims/php"
    ```
   
# Restart requirements

- Changes in:
  - `config/*` (Basically any change in the configuration if it is feature related)
  - `src/Message/*`
  - `src/MessageHandler/*`
- Usage of 2-Stage-Migration in case of renaming a field
   - Stage 1: (Minor Update)
     - Add the new field to the entity
     - Migrate the data from the old field to the new field
     - Update usage of the old field to the new field
     - Entity:
       - Properties:
         - Keep the old field in the entity
         - Add the new field to the entity
       - Methods:
         - Remove getter and setter for the old field (Or deprecate them)
         - Add getter and setter for the new field
           - Setter for the new field should also set the old field (Someone might still use the old field)
           - Getter for the new field should return the new field and if it is null the old field
   - Stage 2: (Major Update)
     - **Must** be deployed in a separate deployment!
     - **Must** be deployed after all workers are stopped! (Basically all processes which uses the old field, cached data might still be used, even the frontend should be stopped)
     - A Worker might still use the old field, that's why this steps will need to be done in a major update.
     - Migrate the data from the old field to the new field (Just to be sure, as it could be that someone wrote to the old field in the meantime)
     - Remove the old field from the entity