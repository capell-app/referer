<?php

declare(strict_types=1);

return [
    'navigation' => 'Referrers',
    'title' => 'Referrers',
    'subtitle' => 'Privacy-preserving aggregate counts of successful HTML page requests by referral source.',
    'health' => [
        'storage_label' => 'Referrer counter storage',
        'storage_passed' => 'Daily, lifetime, and retention-state referrer tables are available.',
        'storage_failed' => 'Referrer counter storage is unavailable.',
        'storage_remediation' => 'Run the Referers package migrations.',
        'middleware_label' => 'Frontend referrer middleware',
        'middleware_passed' => 'The referrer middleware is registered after the anonymous HTML cache boundary.',
        'middleware_failed' => 'The referrer middleware is not registered.',
        'middleware_remediation' => 'Clear the application bootstrap cache and verify the package is installed.',
        'write_label' => 'Referrer counter writes',
        'write_passed' => 'No recent referrer counter write failure has been recorded.',
        'write_failed' => 'A recent referrer counter write failed; page delivery remains unaffected.',
        'write_remediation' => 'Inspect database connectivity and the application log before retrying collection.',
    ],
];
