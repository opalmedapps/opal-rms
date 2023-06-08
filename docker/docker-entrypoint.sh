#!/bin/bash

set -e

# Start the cron scheduler service
service cron start

# Keep the container running
# TODO: remove this "indefinitely running contianer" trick once the Dockerfile/cron re-designed
tail -f /dev/null
