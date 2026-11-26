<?php

namespace App\Contracts;

interface VsmClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function projects(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function materials(): array;

    /**
     * @return array{bom_numbers: array<int, string>, po_numbers: array<int, string>}
     */
    public function bomAndPoNumbersByProject(string $projectCode): array;
}
