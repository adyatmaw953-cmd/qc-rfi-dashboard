<?php

namespace App\Contracts;

interface MaterialInClient
{
    /**
     * Search and retrieve unique supplier names from the material incoming API.
     *
     * @return array<int, string>
     */
    public function searchSuppliers(string $query = ''): array;

    /**
     * Search and retrieve reference numbers (Surat Jalan No) from the material incoming API.
     *
     * @return array<int, array{reference_number: string, supplier: ?string, po_number: ?string, date: ?string}>
     */
    public function searchReferenceNumbers(string $query = ''): array;
}
