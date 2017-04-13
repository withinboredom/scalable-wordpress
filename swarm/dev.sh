#!/bin/bash

source swarm/turn-up.sh

dockerDeploy "wordpress-dev"
dockerDeploy "master-dev"
