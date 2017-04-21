#!/bin/bash

echo "Initializing environment"
if [ ! -e ./.secrets/ ] ; then
    echo "Setting secrets to default. See .secrets directory"
    mkdir -p ./.secrets/
    openssl rand -base64 32 > ./.secrets/mysql_password
    openssl rand -base64 32 > ./.secrets/mysql_root_password
    openssl rand -base64 32 > ./.secrets/xtrabackup_password
    openssl rand -base64 32 > ./.secrets/AUTH_KEY
    openssl rand -base64 32 > ./.secrets/SECURE_AUTH_KEY
    openssl rand -base64 32 > ./.secrets/LOGGED_IN_KEY
    openssl rand -base64 32 > ./.secrets/NONCE_KEY
    openssl rand -base64 32 > ./.secrets/AUTH_SALT
    openssl rand -base64 32 > ./.secrets/SECURE_AUTH_SALT
    openssl rand -base64 32 > ./.secrets/LOGGED_IN_SALT
    openssl rand -base64 32 > ./.secrets/NONCE_SALT
fi
