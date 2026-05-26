<?php
// Quick query to check BPS WebAPI UHH / IPM data for Denpasar (5171)
$key = 'f7899a7f09e8352f04ad230dc7ad19fd';
$domain = '5171';

// Let's query UHH variable (32 or 238)
$url = "https://webapi.bps.go.id/v1/api/list/model/data/lang/ind/domain/{$domain}/key/{$key}/var/238/";
echo "Fetching UHH (var 238)...\n";
$res = file_get_contents($url);
$data = json_decode($res, true);
echo "Status: " . ($data['status'] ?? 'ERROR') . "\n";
if (isset($data['datacontent'])) {
    print_r($data['datacontent']);
} else {
    echo "No datacontent\n";
}

$url2 = "https://webapi.bps.go.id/v1/api/list/model/data/lang/ind/domain/{$domain}/key/{$key}/var/32/";
echo "Fetching UHH (var 32)...\n";
$res2 = file_get_contents($url2);
$data2 = json_decode($res2, true);
echo "Status: " . ($data2['status'] ?? 'ERROR') . "\n";
if (isset($data2['datacontent'])) {
    print_r($data2['datacontent']);
} else {
    echo "No datacontent\n";
}

$url3 = "https://webapi.bps.go.id/v1/api/list/model/data/lang/ind/domain/{$domain}/key/{$key}/var/237/";
echo "Fetching IPM (var 237)...\n";
$res3 = file_get_contents($url3);
$data3 = json_decode($res3, true);
echo "Status: " . ($data3['status'] ?? 'ERROR') . "\n";
if (isset($data3['datacontent'])) {
    print_r($data3['datacontent']);
} else {
    echo "No datacontent\n";
}
