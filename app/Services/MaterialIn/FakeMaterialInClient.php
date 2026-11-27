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
     * @var array<int, array{reference_number: string, supplier: ?string, po_number: ?string, date: ?string}>
     */
    protected array $referenceNumbers = [
        [
            'reference_number' => 'IN-20261120-4AD5',
            'supplier' => 'SDC Cibinong',
            'po_number' => 'PO-2026-001',
            'date' => '2026-11-20',
        ],
        [
            'reference_number' => 'IN-20261120-3443',
            'supplier' => 'SDC Cibinong',
            'po_number' => null,
            'date' => '2026-11-20',
        ],
        [
            'reference_number' => 'IN-20261117-33C0',
            'supplier' => 'Samator',
            'po_number' => 'PO-2026-002',
            'date' => '2026-11-17',
        ],
    ];

    /**
     * @param  array<int, string>  $suppliers
     * @param  array<int, array{reference_number: string, supplier: ?string, po_number: ?string, date: ?string}>  $referenceNumbers
     */
    public function __construct(array $suppliers = [], array $referenceNumbers = [])
    {
        if (! empty($suppliers)) {
            $this->suppliers = $suppliers;
        }

        if (! empty($referenceNumbers)) {
            $this->referenceNumbers = $referenceNumbers;
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

    /**
     * @return array<int, array{reference_number: string, supplier: ?string, po_number: ?string, date: ?string}>
     */
    public function searchReferenceNumbers(string $query = ''): array
    {
        if (trim($query) === '') {
            return $this->referenceNumbers;
        }

        $filtered = array_filter($this->referenceNumbers, function (array $item) use ($query): bool {
            return str_contains(strtolower($item['reference_number']), strtolower(trim($query)))
                || ($item['supplier'] && str_contains(strtolower($item['supplier']), strtolower(trim($query))));
        });

        return array_values($filtered);
    }
}
