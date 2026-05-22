<?php

require __DIR__ . '/../vendor/autoload.php';

use function Cortex\JsonRepair\json_repair;

$testCases = [
    // 1. Trailing comma in objects and arrays
    'trailing_comma' => '{"name": "Sleman", "data": [10, 20, 30,],}',
    // 2. Single quotes
    'single_quotes' => "{'name': 'Sleman', 'data': [10, 20]}",
    // 3. Unquoted keys
    'unquoted_keys' => "{name: \"Sleman\", data: [10, 20]}",
    // 4. Comments inside JSON
    'comments' => '{
        // Data for Sleman
        "name": "Sleman",
        /* dataset */
        "data": [10, 20]
    }',
    // 5. Nested objects with trailing comma and single quotes
    'nested_dirty' => "{
        'type': 'bar',
        'data': {
            'labels': ['A', 'B',],
            'datasets': [
                { 'label': 'Sleman', 'data': [1.2, 3.4], }
            ]
        }
    }"
];

echo "=== Running JSON Repair Tests ===\n\n";

foreach ($testCases as $name => $dirtyJson) {
    echo "--- Case: $name ---\n";
    echo "Raw: " . trim($dirtyJson) . "\n";
    try {
        $repaired = json_repair($dirtyJson);
        echo "Repaired: $repaired\n";
        $decoded = json_decode($repaired, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "Decoded Successfully!\n";
            print_r($decoded);
        } else {
            echo "Decode Failed: " . json_last_error_msg() . "\n";
        }
    } catch (\Exception $e) {
        echo "Repair Failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
