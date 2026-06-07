<?php

namespace App\Support;

/**
 * Outcome of a product CSV import: how many records were created, updated or
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
    ) {}

    public function recordRow(int $rowNumber, string $message): void
    {
        $this->skipped++;
        $this->errors[] = "Строка {$rowNumber}: {$message}";
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
