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
}
