#!/bin/bash

namespace=$1

if [ "$namespace" == "" ]; then
    namespace="blog"
fi

function running() {
    echo $(docker service ls --filter name="$1" | tr -s ' ' | cut -d ' ' -f 4 | tail -n 1 | cut -d '/' -f 1)
}

function expected() {
    echo $(docker service ls --filter name="$1" | tr -s ' ' | cut -d ' ' -f 4 | tail -n 1 | cut -d '/' -f 2)
}

function nameMe() {
    echo "${namespace}_$1"
}

function waitFor() {
    declare name=$(nameMe $1)
    declare runningReplicas=$(running "$name")
    declare expectedReplicas=$(expected "$name")
    declare last="$runningReplicas"
    echo "Waiting for $name"
    while [[ "$runningReplicas" == 'REPLICAS' || "$runningReplicas" != "$expectedReplicas" ]]; do
        runningReplicas=$(running "$name")
        expectedReplicas=$(expected "$name")
        if [[ "$last" != "$runningReplicas" ]]; then
            echo "$runningReplicas of $expectedReplicas up"
            last="$runningReplicas"
        fi
        sleep 1
    done
    return 0
}

function isNotUp() {
    declare name=$(nameMe $1)
    if [[ $(docker service ls --filter name="$name" --quiet | wc -l | tr -d ' ') == "1" ]]; then
        return 1
    else
        return 0
    fi
}

function isNotScaled() {
    declare name=$(nameMe $1)
    if ! isNotUp $1; then
        if [[ $(running "$name") != "$2" ]]; then
            return 0
        fi
    fi

    return 1
}

function dockerDeploy() {
    docker stack deploy -c swarm/$1.yml "$namespace"
}

function dockerScale() {
    docker service scale $(nameMe $1)=$2
}

function deploy() {
    if [[ "$2" == "" ]]; then
        configFile="$1"
    else
        configFile="$2"
    fi

    if isNotUp "$1"; then
        dockerDeploy "$configFile"
        waitFor "$1"
    else
        echo "$1 already up for $namespace"
    fi
}

function scale() {
    if isNotScaled "$1" "$2"; then
        dockerScale "$1" "$2"
        waitFor "$1"
    else
        echo "$1 already scaled to $2"
    fi
}

dockerDeploy "secrets"

deploy "seed"
deploy "node"

scale "node" 3
scale "seed" 0

deploy "memcached"
deploy "phpsysinfo"
deploy "master"
deploy "wordpress"

deploy "traefik"

docker service update $(nameMe 'traefik') --publish-rm 443
docker service update $(nameMe 'traefik') --publish-add mode=host,target=443,published=443,protocol=tcp
