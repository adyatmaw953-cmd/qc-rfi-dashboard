<?php

return [
    'modules' => [
        'rfi' => [
            'label' => 'RFI',
            'route' => 'rfi.index',
            'roles' => ['admin', 'production', 'qc_qa', 'warehouse', 'ppic'],
        ],
        'qc_finding' => [
            'label' => 'QC Finding',
            'route' => 'qc-finding.index',
            'roles' => ['admin', 'production', 'qc_qa', 'warehouse', 'ppic'],
        ],
        'ncr' => [
            'label' => 'NCR',
            'route' => 'ncr.index',
            'roles' => ['admin', 'qc_qa'],
        ],
        'mdr' => [
            'label' => 'MDR',
            'route' => 'mdr-checklist.index',
            'roles' => ['admin', 'qc_qa'],
        ],
    ],

    'mdr_reports' => [
        'certificate_of_conformance' => 'Certificate of Conformance',
        'certificate_of_manufacture' => 'Certificate of Manufacture',
        'design_drawing_approval' => 'Design Drawing & Approval',
        'wps' => 'WPS',
        'welder_cert' => 'Welder Cert',
        'incoming_material' => 'Incoming Material',
        'mill_certificate' => 'Mill Certificate',
        'dimensional_report' => 'Dimensional Report',
        'fabrication_report' => 'Fabrication Report',
        'welding_report' => 'Welding Report',
        'ndt_report' => 'NDT Report',
        'hydrotest_report' => 'Hydrotest Report',
        'lifting_test_report' => 'Lifting Test Report',
        'leak_test_report' => 'Leak Test Report',
        'painting_report' => 'Painting Report',
        'certificate_of_galvanize' => 'Certificate of Galvanize',
    ],

    'rfi_common_fields' => [
        [
            'name' => 'project_id',
            'label' => 'Project',
            'type' => 'project',
            'required' => true,
        ],
        [
            'name' => 'date_inspect',
            'label' => 'Tanggal inspeksi',
            'type' => 'date',
            'required' => true,
        ],
        [
            'name' => 'note',
            'label' => 'Catatan',
            'type' => 'textarea',
            'required' => false,
        ],
    ],

    'findings' => [
        // Roles that may fill or update the action plan per RFI category (admin is always allowed)
        'action_plan_roles' => [
            'B' => ['production'],
            'C' => ['production'],
            'D' => ['production'],
            'E' => ['ppic'],
            'F' => ['ppic'],
        ],

        // Roles that may send a Re-Inspect submission with evidence per RFI category (admin is always allowed)
        'submission_roles' => [
            'B' => ['production'],
            'C' => ['production'],
            'D' => ['production'],
            'E' => ['ppic'],
            'F' => ['ppic'],
        ],

        // Which finding categories each role may view (list, project detail, charts, download)
        'view_categories' => [
            'admin' => ['A', 'B', 'C', 'D', 'E', 'F'],
            'qc_qa' => ['A', 'B', 'C', 'D', 'E', 'F'],
            'production' => ['A', 'B', 'C', 'D', 'E'],
            'warehouse' => ['A'],
            'ppic' => ['E', 'F'],
        ],
    ],

    'rfi_categories' => [
        'A' => [
            'label' => 'Incoming Material',
            'roles' => ['admin', 'qc_qa', 'warehouse'],
            'submit_roles' => ['warehouse'],
            'drafts' => true,
            'general_fields' => [
                ['name' => 'surat_jalan_no', 'label' => 'Surat Jalan No', 'type' => 'text', 'required' => true],
                ['name' => 'po_no', 'label' => 'PO No', 'type' => 'text', 'required' => true],
                ['name' => 'vendor', 'label' => 'Vendor', 'type' => 'text', 'required' => true],
                ['name' => 'bom_no', 'label' => 'BOM No', 'type' => 'text', 'required' => false],
            ],
            'fields' => [],
        ],
        'B' => [
            'label' => 'Preparation',
            'roles' => ['admin', 'qc_qa', 'production'],
            'submit_roles' => ['production'],
            'general_fields' => [
                ['name' => 'item_name', 'label' => 'Item Name', 'type' => 'text', 'required' => true],
                ['name' => 'mtr_no', 'label' => 'MTR no.', 'type' => 'text', 'required' => true],
                ['name' => 'drawing_no', 'label' => 'Drawing no', 'type' => 'text', 'required' => true],
                ['name' => 'inspect_item', 'label' => 'Item inspeksi', 'type' => 'select', 'required' => true, 'options' => ['Cutting']],
            ],
            'fields' => [],
        ],
        'C' => [
            'label' => 'Fabrication',
            'roles' => ['admin', 'qc_qa', 'production'],
            'submit_roles' => ['production'],
            'general_fields' => [
                ['name' => 'drawing_no', 'label' => 'Drawing No dari PPIC', 'type' => 'text', 'required' => true],
                ['name' => 'inspect_item', 'label' => 'Item inspeksi', 'type' => 'select', 'required' => true, 'options' => ['Fit Up', 'Welding', 'Before Blasting', 'Testing']],
                ['name' => 'test_type', 'label' => 'Jenis pengujian', 'type' => 'multi_select', 'required' => false, 'required_if' => ['field' => 'inspect_item', 'value' => 'Testing'], 'show_if' => ['field' => 'inspect_item', 'value' => 'Testing'], 'options' => ['Load', 'Leak Test', 'NDT', 'Hydro']],
            ],
            'fields' => [],
        ],
        'D' => [
            'label' => 'Finishing',
            'roles' => ['admin', 'qc_qa', 'production'],
            'submit_roles' => ['production'],
            'general_fields' => [
                ['name' => 'drawing_no', 'label' => 'Drawing No', 'type' => 'text', 'required' => true],
                ['name' => 'inspect_item', 'label' => 'Item inspeksi', 'type' => 'select', 'required' => true, 'options' => ['Secondary Coat', 'Final Inspection', 'Before Delivery']],
            ],
            'fields' => [],
        ],
        'E' => [
            'label' => 'Vendor',
            'roles' => ['admin', 'qc_qa', 'ppic'],
            'submit_roles' => ['ppic'],
            'general_fields' => [
                ['name' => 'drawing_no', 'label' => 'Drawing No', 'type' => 'text', 'required' => true],
                ['name' => 'process', 'label' => 'Proses', 'type' => 'text', 'required' => true],
                ['name' => 'scheduled_at', 'label' => 'Jadwal inspeksi', 'type' => 'date', 'required' => true],
            ],
            'fields' => [],
        ],
        'F' => [
            'label' => 'Inspection by Customer',
            'roles' => ['admin', 'qc_qa', 'ppic'],
            'submit_roles' => ['ppic'],
            'general_fields' => [
                [
                    'name' => 'jenis_inspeksi',
                    'label' => 'Jenis inspeksi',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        'Material inspection',
                        'Fit up inspection',
                        'Welding inspection',
                        'Load test',
                        'NDE test',
                        'Painting inspection',
                        'Final inspection',
                        'Additional',
                    ],
                ],
                [
                    'name' => 'additional_name',
                    'label' => 'Nama inspeksi tambahan',
                    'type' => 'text',
                    'required' => false,
                    'required_if' => [
                        'field' => 'jenis_inspeksi',
                        'value' => 'Additional',
                    ],
                    'show_if' => [
                        'field' => 'jenis_inspeksi',
                        'value' => 'Additional',
                    ],
                ],
            ],
            'fields' => [],
        ],
    ],

    'notifications' => [
        'rfi.submitted' => [
            'roles' => ['qc_qa', 'admin'],
            'accounts' => [],
        ],
        'rfi.reviewed_ok' => [
            'roles' => [],
            'accounts' => ['rfi.created_by'],
        ],
        'rfi.reviewed_nc' => [
            'roles' => ['production'],
            'accounts' => ['rfi.created_by'],
        ],
        'finding.reinspect_submitted' => [
            'roles' => ['qc_qa', 'admin'],
            'accounts' => [],
        ],
        'finding.verified_ok' => [
            'roles' => [],
            'accounts' => ['submission.submitted_by', 'finding.action_plan_by', 'rfi.created_by'],
        ],
        'finding.verified_rejected' => [
            'roles' => ['production'],
            'accounts' => ['submission.submitted_by', 'finding.action_plan_by'],
        ],
        'finding.due_date_changed' => [
            'roles' => ['production'],
            'accounts' => ['finding.action_plan_by'],
        ],
    ],
];
