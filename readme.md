# An Easy to Scale Vanilla WordPress

This is based on lots of information around the web:

- https://github.com/christianc1/wordpress-memcached
- https://github.com/colinmollenhour/mariadb-galera-swarm
- https://raw.githubusercontent.com/docker-library/wordpress/master/php7.1/apache/docker-entrypoint.sh

# How to use it

Using this repository is relatively easy...

## Docker Compose

Simply run `./init.sh` in the root of the repo. Or, manually:

```sh
# Create the secrets
mkdir .secrets
echo "password" > .secrets/mysql_password
echo "password" > .secrets/mysql_root_password
echo "password" > .secrets/xtrabackup_password

# Start the database services
docker-compose up -d seed
docker-compose scale node=2

# Once the database is up and running:
docker-compose scale node=3 seed=0

# and now memcached
docker-compose scale memcached=1

# and finally, WordPress itself:
docker-compose scale wordpress=1
```

## Docker Swarm

Coming soon!