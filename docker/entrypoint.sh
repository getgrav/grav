#!/bin/bash

set -e

if [ "$1" = 'apache2-foreground' ]; then
    exec "$@"
else
    /bin/bash -l -c "$@"
fi
