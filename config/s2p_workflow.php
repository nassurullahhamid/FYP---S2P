<?php

return [
    'version' => 2,

    'roles' => [
        'admin' => 'Admin',
        'ketua_upp' => 'KUPP',
        'ketua_utd' => 'KUTD',
        'ketua_wilayah' => 'KW',
        'juruteknik' => 'JTK',
    ],

    'classification_roles' => [
        'ketua_upp',
        'ketua_utd',
        'ketua_wilayah',
    ],

    'statuses' => [
        'classification' => 'Menunggu Klasifikasi',
        'initial_review' => 'Menunggu Semakan',
        'reviewed' => 'Disemak',
        'in_progress' => 'Dalam Tindakan',
        'confirmation' => 'Menunggu Pengesahan',
        'report_review' => 'Menunggu Semakan Laporan',
        'pic_correction' => 'Laporan Perlu Pembetulan',
        'ready_for_verification' => 'Sedia Diverifikasi',
        'validation' => 'Menunggu Validasi',
        'chief_correction' => 'Pembetulan Ketua',
        'completed' => 'Selesai',
    ],

    'sla_days' => [
        'Tinggi' => 3,
        'Sederhana' => 7,
        'Rendah' => 14,
    ],

    'categories' => [
        'Meja Bantuan' => [
            'Penyelenggaraan Komputer',
            'Penyelenggaraan Rangkaian',
            'Sistem Aplikasi',
            'Perkhidmatan E-mel',
            'Perkhidmatan Lintas Langsung',
            'Peminjaman Peralatan ICT',
        ],
        'Konsultasi Rangkaian' => [
            'Pemasangan Baharu',
            'Naiktaraf',
        ],
        'Transformasi Digital' => [
            'Pemodenan Bilik Mesyuarat',
            'Pembekalan Peralatan ICT',
        ],
    ],

    'flows' => [
        'peminjaman' => [
            'kategori' => 'Meja Bantuan',
            'sub_kategori' => ['Peminjaman Peralatan ICT'],
            'review_role' => 'ketua_upp',
            'assignment_role' => 'ketua_upp',
            'pic_submission_role' => 'ketua_utd',
            'confirmation_role' => 'ketua_utd',
            'verification_role' => null,
            'validation_role' => null,
        ],

        'meja_bantuan' => [
            'kategori' => 'Meja Bantuan',
            'sub_kategori' => [
                'Penyelenggaraan Komputer',
                'Penyelenggaraan Rangkaian',
                'Sistem Aplikasi',
                'Perkhidmatan E-mel',
                'Perkhidmatan Lintas Langsung',
            ],
            'review_role' => 'ketua_utd',
            'assignment_role' => 'ketua_utd',
            'pic_submission_role' => 'ketua_utd',
            'confirmation_role' => 'ketua_utd',
            'verification_role' => null,
            'validation_role' => null,
        ],

        'rangkaian' => [
            'kategori' => 'Konsultasi Rangkaian',
            'sub_kategori' => ['Pemasangan Baharu', 'Naiktaraf'],
            'review_role' => 'ketua_utd',
            'assignment_role' => 'ketua_utd',
            'pic_submission_role' => 'ketua_utd',
            'confirmation_role' => null,
            'verification_role' => 'ketua_utd',
            'validation_role' => 'ketua_wilayah',
            'report_print_roles' => ['ketua_utd', 'ketua_wilayah'],
        ],

        'pemodenan' => [
            'kategori' => 'Transformasi Digital',
            'sub_kategori' => ['Pemodenan Bilik Mesyuarat'],
            'review_role' => 'ketua_upp',
            'assignment_role' => 'ketua_utd',
            'pic_submission_role' => 'ketua_upp',
            'confirmation_role' => null,
            'verification_role' => 'ketua_upp',
            'validation_role' => 'ketua_wilayah',
            'report_print_roles' => ['ketua_upp', 'ketua_wilayah'],
        ],

        'pembekalan' => [
            'kategori' => 'Transformasi Digital',
            'sub_kategori' => ['Pembekalan Peralatan ICT'],
            'review_role' => 'ketua_upp',
            'assignment_role' => 'ketua_utd',
            'pic_submission_role' => 'ketua_upp',
            'confirmation_role' => null,
            'verification_role' => 'ketua_upp',
            'validation_role' => 'ketua_wilayah',
            'report_print_roles' => ['ketua_upp', 'ketua_wilayah'],
        ],
    ],

    'trail_events' => [
        'registered' => 'DAFTAR TIKET',
        'classified' => 'DIKLASIFIKASI',
        'reviewed' => 'DISEMAK',
        'performed' => 'DILAKSANA',
        'confirmed' => 'DISAHKAN',
        'verified' => 'DIVERIFIKASI',
        'validated' => 'DIVALIDASI',
        'correction_requested' => 'PEMBETULAN DIMINTA',
        'closed' => 'TIKET DITUTUP',
    ],
];
