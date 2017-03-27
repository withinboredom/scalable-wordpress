#!/bin/bash

docker build -f wordpress.dockerfile -t withinboredom/scalable-wordpress:latest --cache-from withinboredom/scalable-wordpress .