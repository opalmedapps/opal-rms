<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Patient\Model;

/** @psalm-immutable */
class Mrn
{
    public function __construct(
        public string $mrn,
        public string $site,
        public bool $active
    ) {}
}
