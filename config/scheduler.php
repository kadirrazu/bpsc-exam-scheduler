<?php

return [
    'version' => '1.6.3',
    'api_token_hours' => 24,
    'max_export_rows' => 10000,
    'status_colors' => ['proposed' => 'FFF4CC', 'scheduled' => 'E6F0FF', 'completed' => 'E0F2E5', 'postponed' => 'FCE8D5', 'cancelled' => 'FCE3E3'],
    'statuses' => ['proposed' => 'Proposed', 'scheduled' => 'Scheduled', 'postponed' => 'Postponed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'],
    'units' => [...array_map(fn ($n) => sprintf('Unit %02d', $n), range(1, 20)), 'Non Cadre (Exam)', 'Cadre (Exam)'],
    'user_units' => [...array_map(fn ($n) => sprintf('Unit %02d', $n), range(1, 20)), 'Non Cadre (Exam)', 'Cadre (Exam)', 'Non Cadre (Confidential)', 'Cadre (Confidential)', 'IT Section', 'Administration Wing', 'Law Wing'],
    'types' => [
        'preliminary' => ['label' => 'Preliminary (MCQ Type)', 'viva' => false],
        'written' => ['label' => 'Written', 'viva' => false],
        'viva' => ['label' => 'Viva', 'viva' => true],
        'departmental' => ['label' => 'Departmental', 'viva' => false],
        'senior_scale' => ['label' => 'Senior Scale', 'viva' => false],
    ],
];
