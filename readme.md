[![](https://images.microbadger.com/badges/image/withinboredom/scalable-wordpress.svg)](https://microbadger.com/images/withinboredom/scalable-wordpress "Get your own image badge on microbadger.com")

# An Easy to Scale Vanilla WordPress

Getting WordPress to scale in docker swarm mode is a fairly complex feat.
 
This repository serves as a template to get you started. Fork it, modify it and then deploy it.

## Features

- Updating or adding plugins to a deployment is easy and straightforward
- Galera cluster for the database
- Auto configuration of memcached
- Memcached and Batcache
- Passwords stored in secrets
- Production ready
- Traefik load balancer

## Adding plugins and themes

Simply update the list in `deploy_phase_4.yml` or `docker-compose.yml` and redeploy

## Credits

This is based on lots of information around the web:

- https://github.com/christianc1/wordpress-memcached
- https://github.com/colinmollenhour/mariadb-galera-swarm
- https://raw.githubusercontent.com/docker-library/wordpress/master/php7.1/apache/docker-entrypoint.sh
- https://github.com/php-memcached-dev/php-memcached/tree/master
- https://github.com/igbinary/igbinary

# Deploying

This is only meant to be deployed on Docker 1.13+ swarm mode.

## Docker Swarm

Deploying with Docker Swarm for the first time:

From the root of the repo, run `swarm/turn-up.sh`, the output should look similar to:

```sh
Creating secret blog_xtrabackup_password
Creating secret blog_SECURE_AUTH_SALT
Creating secret blog_NONCE_SALT
Creating secret blog_AUTH_KEY
Creating secret blog_LOGGED_IN_SALT
Creating secret blog_LOGGED_IN_KEY
Creating secret blog_mysql_root_password
Creating secret blog_NONCE_KEY
Creating secret blog_AUTH_SALT
Creating secret blog_mysql_password
Creating secret blog_SECURE_AUTH_KEY
Creating network blog_default
Creating service blog_seed
Waiting for blog_seed
1 of 1 up
Creating service blog_node
Waiting for blog_node
1 of 2 up
2 of 2 up
blog_node scaled to 3
Waiting for blog_node
3 of 3 up
blog_seed scaled to 0
Waiting for blog_seed
Creating service blog_memcached
Waiting for blog_memcached
2 of 2 up
Creating service blog_phpsysinfo
Waiting for blog_phpsysinfo
1 of 1 up
Creating service blog_master
Waiting for blog_master
1 of 1 up
Creating service blog_wordpress
Waiting for blog_wordpress
2 of 2 up
Creating service blog_traefik
Waiting for blog_traefik
1 of 1 up
blog_traefik
blog_traefik
```

If you'd like to enable developer mode (single node swarm only) -- run `swarm/dev.sh` from the root of the repo.
