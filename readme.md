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

Using this repository is relatively easy...There's "developer mode" using Docker Compose, and a 
phased deployment using Docker Swarm

## Docker Compose

Simply run `./init.sh` in the root of the repo.

Some useful scaling:

`docker-compose scale wordpress=2 memcached=2`

## Docker Swarm

Deploying with Docker Swarm for the first time:

Start the db cluster:
```sh
docker stack deploy -c deploy_phase_1.yml blog && watch docker service ls

# output
ID            NAME            MODE        REPLICAS  IMAGE
nue7n61snea1  blog_node       replicated  0/0       colinmollenhour/mariadb-galera-swarm:latest
pzanqapw0vw8  blog_wordpress  replicated  0/0       withinboredom/scalable-wordpress:latest
rhlqgvede3td  blog_seed       replicated  0/1       colinmollenhour/mariadb-galera-swarm:latest <-- wait for this one
shq3qpuf3afd  blog_memcached  replicated  2/2       memcached:latest
yju62pr7pvfx  blog_traefik    replicated  1/1       traefik:latest
```

Wait for the blog_seed replica to be ready and then, continue to phase 2:
```sh
docker stack deploy -c deploy_phase_2.yml blog && watch docker service ls

# output
ID            NAME            MODE        REPLICAS  IMAGE
2o04yu9m9cta  blog_seed       replicated  1/1       colinmollenhour/mariadb-galera-swarm:latest
f8pdkh3lb2ch  blog_traefik    replicated  1/1       traefik:latest
gtt0gkg3ja90  blog_wordpress  replicated  0/0       withinboredom/scalable-wordpress:latest
l0a8hpmr2rez  blog_node       replicated  0/2       colinmollenhour/mariadb-galera-swarm:latest <-- wait for this one
xfj0cb90nhbm  blog_memcached  replicated  2/2       memcached:latest
```

Once blog_node is ready, continue to phase 3:

```sh
docker stack deploy -c deploy_phase_3.yml blog && watch docker service ls

# output
ID            NAME            MODE        REPLICAS  IMAGE
2o04yu9m9cta  blog_seed       replicated  0/0       colinmollenhour/mariadb-galera-swarm:latest
f8pdkh3lb2ch  blog_traefik    replicated  1/1       traefik:latest
gtt0gkg3ja90  blog_wordpress  replicated  0/2       withinboredom/scalable-wordpress:latest <-- wait for this one
l0a8hpmr2rez  blog_node       replicated  2/3       colinmollenhour/mariadb-galera-swarm:latest <-- wait for this one
xfj0cb90nhbm  blog_memcached  replicated  2/2       memcached:latest
```

At this point, the `wordpress` service should start coming up. Once available, navigate to the host 
(ex: http://www.withinboredom.info) and install WordPress. Once you have it set up,
you're ready to continue to phase 4:

```sh
docker stack deploy -c deploy_phase_4.yml blog && watch docker service ls

# output
ID            NAME            MODE        REPLICAS  IMAGE
2o04yu9m9cta  blog_seed       replicated  0/0       colinmollenhour/mariadb-galera-swarm:latest
f8pdkh3lb2ch  blog_traefik    replicated  1/1       traefik:latest
gtt0gkg3ja90  blog_wordpress  replicated  1/2       withinboredom/scalable-wordpress:latest
l0a8hpmr2rez  blog_node       replicated  3/3       colinmollenhour/mariadb-galera-swarm:latest
xfj0cb90nhbm  blog_memcached  replicated  2/2       memcached:latest
```

Each new container will include the themes and plugins defined in the `PLUGINS` and `THEMES` environment
variables.
