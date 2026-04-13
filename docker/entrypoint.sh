#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

mkdir -p config/jwt var/cache var/log

if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
  openssl genpkey \
    -algorithm RSA \
    -out config/jwt/private.pem \
    -aes256 \
    -pass pass:"${JWT_PASSPHRASE}" \
    -pkeyopt rsa_keygen_bits:2048

  openssl pkey \
    -in config/jwt/private.pem \
    -out config/jwt/public.pem \
    -pubout \
    -passin pass:"${JWT_PASSPHRASE}"
fi

php bin/console cache:clear --no-warmup >/dev/null 2>&1 || true

if [ "${AUTO_MIGRATE:-0}" = "1" ]; then
  php bin/console doctrine:migrations:migrate --no-interaction
fi

if [ "${AUTO_SEED_DEMO:-0}" = "1" ]; then
  php bin/console app:seed-demo-data
fi

exec "$@"
