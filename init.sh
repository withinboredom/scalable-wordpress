#!/bin/bash

echo "Initializing environment"
if [ ! -e ./.secrets/ ] ; then
    echo "Setting secrets to default ... which is insecure. See .secrets directory"
    mkdir -p ./.secrets/
    echo "password" > ./.secrets/mysql_password
    echo "password" > ./.secrets/mysql_root_password
    echo "password" > ./.secrets/xtrabackup_password
fi

docker-compose down -v

docker-compose up -d seed
sleep 10
docker-compose up -d node
docker-compose scale node=2
sleep 30
docker-compose scale node=3 seed=0
docker-compose up -d wordpress