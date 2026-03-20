<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Page Graph Widget',
    'description' => 'Dashboard widget showing the TYPO3 page tree and content elements as an interactive force-directed graph visualization.',
    'category' => 'be',
    'author' => 'Roland Tfirst',
    'author_email' => 'roland@tfirst.de',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'dashboard' => '12.4.0-14.99.99',
        ],
    ],
];
