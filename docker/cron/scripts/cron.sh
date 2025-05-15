#!/bin/bash

set -eu

exec busybox crond -f -l 8 -L /dev/stdout
