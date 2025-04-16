<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Diagnosis\Model;

/** @psalm-immutable */
/** @psalm-suppress PossiblyUnusedProperty */
class Diagnosis
{
    public function __construct(
        public int $id,
        public string $subcode,
        public string $subcodeDescription,
        public string $code,
        public string $codeDescription,
        public string $codeCategory,
        public string $chapter,
        public string $chapterDescription,
    ) {}
}
