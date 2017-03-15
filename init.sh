#!/bin/bash

echo "Initializing environment"
if [ ! -e ./.secrets/ ] ; then
    echo "Setting secrets to default ... which is insecure. See .secrets directory"
    mkdir -p ./.secrets/
    openssl rand -base64 32 > ./.secrets/mysql_password
    openssl rand -base64 32 > ./.secrets/mysql_root_password
    openssl rand -base64 32 > ./.secrets/xtrabackup_password
    openssl rand -base64 64 > ./.secrets/AUTH_KEY
    openssl rand -base64 64 > ./.secrets/SECURE_AUTH_KEY
    openssl rand -base64 64 > ./.secrets/LOGGED_IN_KEY
    openssl rand -base64 64 > ./.secrets/NONCE_KEY
    openssl rand -base64 64 > ./.secrets/AUTH_SALT
    openssl rand -base64 64 > ./.secrets/SECURE_AUTH_SALT
    openssl rand -base64 64 > ./.secrets/LOGGED_IN_SALT
    openssl rand -base64 64 > ./.secrets/NONCE_SALT
fi

docker-compose down -v

docker-compose up -d seed
sleep 10
docker-compose scale node=2 memcached=1
sleep 30
docker-compose scale node=3 seed=0
docker-compose up -d wordpress