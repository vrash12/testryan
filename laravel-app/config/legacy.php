<?php

return [
    'source_connection' => 'legacy',
    'target_connection' => 'mysql',
    // Explicit local schemas are also checked against the actual server/database.
    'allowed_source_databases' => ['nestcrafters_m2_legacy', 'nestcrafters_m2_source_test'],
    'allowed_target_databases' => ['nestcrafters_local', 'nestcrafters_m2_test'],
    'test_connection' => 'm2_test',
    'test_source_connection' => 'm2_source_test',
];
