<?php

declare(strict_types=1);

namespace Orms\Diagnosis\Model;

/** @psalm-immutable */
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
