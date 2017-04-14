#!/bin/bash

curl -sSf -o - localhost:80/wp-cron.php 2>&1 || exit 1

exit 0
