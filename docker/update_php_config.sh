#!/bin/bash

# SPDX-FileCopyrightText: Copyright (C) 2023 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
#
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

# don't expose that PHP is used on server
# https://php.net/expose-php
sed -ri -e 's!expose_php =.*!expose_php = Off!g' /usr/local/etc/php/php.ini
# default timezone to the one Montreal falls in
# https://php.net/date.timezone
sed -ri -e 's!;date.timezone =!date.timezone = America/Toronto!g' /usr/local/etc/php/php.ini
# include environment variables $_ENV as available variables
# https://php.net/variables-order
sed -ri -e 's!variables_order = "GPCS"!variables_order = "EGPCS"!g' /usr/local/etc/php/php.ini
