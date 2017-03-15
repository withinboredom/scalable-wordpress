#!/bin/bash

echo "Initializing environment"
if [ ! -e ./.secrets/ ] ; then
    echo "Setting secrets to default ... which is insecure. See .secrets directory"
    mkdir -p ./.secrets/
    echo "password" > ./.secrets/mysql_password
    echo "password" > ./.secrets/mysql_root_password
    echo "password" > ./.secrets/xtrabackup_password
    echo "salt" > ./.secrets/AUTH_KEY
    echo "salt" > ./.secrets/SECURE_AUTH_KEY
    echo "salt" > ./.secrets/LOGGED_IN_KEY
    echo "salt" > ./.secrets/NONCE_KEY
    echo "salt" > ./.secrets/AUTH_SALT
    echo "salt" > ./.secrets/SECURE_AUTH_SALT
    echo "salt" > ./.secrets/LOGGED_IN_SALT
    echo "salt" > ./.secrets/NONCE_SALT
fi

docker-compose down -v

docker-compose up -d seed
sleep 10
docker-compose scale node=2 memcached=1
sleep 30
docker-compose scale node=3 seed=0
docker-compose up -d wordpress