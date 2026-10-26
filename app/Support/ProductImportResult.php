<?php

namespace App\Support;

/**
 * Outcome of a product import (CSV or FusionPOS): how many records were created, updated or
 * skipped, plus human-readable messages for any rows that failed.
 */
class ProductImportResult
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public array $errors = [],
        public int $deactivated = 0,
        public int $modifierGroups = 0,
        public int $modifiers = 0,
    ) {}

    public function recordRow(int $rowNumber, string $message): void
    {
        $this->recordSkip("Строка {$rowNumber}: {$message}");
    }

    public function recordSkip(string $message): void
    {
        $this->skipped++;
        $this->errors[] = $message;
    }

    public function total(): int
    {
        return $this->created + $this->updated;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
