<?php

namespace App\Services\MaterialIn;

use App\Contracts\MaterialInClient;

class FakeMaterialInClient implements MaterialInClient
{
    /**
     * @var array<int, string>
     */
    protected array $suppliers = [
        'ACC',
        'Alam Lestari',
        'Chinwa',
        'Gala Perdana Agung',
        'Mandiri Abadi Gasindo',
        'Materialize',
        'Samator',
        'SDC Cibinong',
        'TB Buana Jaya',
    ];

    /**
     * @param  array<int, string>  $suppliers
     */
    public function __construct(array $suppliers = [])
    {
        if (! empty($suppliers)) {
            $this->suppliers = $suppliers;
        }
    }

    /**
     * @return array<int, string>
     */
    public function searchSuppliers(string $query = ''): array
    {
        if (trim($query) === '') {
            return $this->suppliers;
        }

        $filtered = array_filter($this->suppliers, function (string $supplier) use ($query): bool {
            return str_contains(strtolower($supplier), strtolower(trim($query)));
        });

        return array_values($filtered);
    }
}
