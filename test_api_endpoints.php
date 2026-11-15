<?php
/**
 * test_api_endpoints.php — End-to-end API Endpoint Verification
 * Tests pages/patient_reminders_api.php via subprocess CLI execution
 */

function runApiSubprocess(string $method, array $get = [], array $post = []): array {
    $phpExe = 'C:\\xampp\\php\\php.exe';
    $script = __DIR__ . '/pages/patient_reminders_api.php';

    $queryString = http_build_query($get);
    $postJson = json_encode($post);

    // Bootstrap script that sets superglobals and requires patient_reminders_api.php
    $bootstrap = <<<PHP
\$_SERVER['REQUEST_METHOD'] = '{$method}';
\$_SERVER['QUERY_STRING'] = '{$queryString}';
parse_str('{$queryString}', \$_GET);
\$_POST = json_decode('{$postJson}', true) ?: [];
\$_SESSION = ['user_id' => 1, 'logged_in' => true];
require '{$script}';
PHP;

    $cmd = sprintf(
        '"%s" -r "%s"',
        $phpExe,
        str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $bootstrap)
    );

    $raw = shell_exec($cmd);
    
    // Find json substring in raw output
    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start !== false && $end !== false) {
        $jsonStr = substr($raw, $start, $end - $start + 1);
        $decoded = json_decode($jsonStr, true);
        if ($decoded) return $decoded;
    }
    return ['raw' => $raw];
}

$passed = 0;
$failed = 0;

function assertApi(string $name, bool $cond, string $details = ''): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo " [PASS] {$name}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo "  AayuVault Patient Reminders HTTP API Endpoint Tests\n";
echo "====================================================================\n\n";

// Test 1: Add Blood Test Reminder via POST
$addRes = runApiSubprocess('POST', ['action' => 'add_reminder'], [
    'title'          => 'Comprehensive Lipid Panel',
    'reminder_type'  => 'Blood Test',
    'reminder_date'  => date('Y-m-d', strtotime('+2 days')),
    'reminder_time'  => '08:30:00',
    'description'    => '12 hours fasting required. Drink plain water only.'
]);
assertApi("API Add Blood Test", !empty($addRes['success']) && !empty($addRes['reminder_id']), "Reminder ID: " . ($addRes['reminder_id'] ?? 'none'));
$createdId = $addRes['reminder_id'] ?? 0;

// Test 2: Add Medicine Reminder via POST
$addMedRes = runApiSubprocess('POST', ['action' => 'add_reminder'], [
    'title'          => 'Atorvastatin 20mg Bedtime',
    'reminder_type'  => 'Medicine',
    'reminder_date'  => date('Y-m-d', strtotime('+1 day')),
    'reminder_time'  => '21:00:00',
    'description'    => 'Take with water at bedtime'
]);
assertApi("API Add Medicine", !empty($addMedRes['success']), "Title: " . ($addMedRes['reminder']['title'] ?? 'none'));

// Test 3: Get Reminders via GET (Validates Upcoming, Past, Completed)
$getRes = runApiSubprocess('GET', ['action' => 'get_reminders']);
assertApi(
    "API Get Reminders (Structure & Sections)",
    !empty($getRes['success']) && isset($getRes['data']['upcoming']) && isset($getRes['data']['past']) && isset($getRes['data']['completed']),
    "Total upcoming: " . count($getRes['data']['upcoming'] ?? []) . ", Total all: " . count($getRes['data']['all'] ?? [])
);

// Test 4: Get Single Reminder
$getSingle = runApiSubprocess('GET', ['action' => 'get_reminder', 'id' => $createdId]);
assertApi("API Get Single Reminder", !empty($getSingle['success']) && ($getSingle['reminder']['title'] ?? '') === 'Comprehensive Lipid Panel');

// Test 5: Update Reminder
$updRes = runApiSubprocess('POST', ['action' => 'update_reminder', 'id' => $createdId], [
    'title'       => 'Comprehensive Lipid Panel (Quest Diagnostics)',
    'description' => 'Fast from 8 PM prior evening. Water permitted.'
]);
assertApi("API Update Reminder", !empty($updRes['success']) && ($updRes['reminder']['title'] ?? '') === 'Comprehensive Lipid Panel (Quest Diagnostics)');

// Test 6: Mark Reminder Status (Completed)
$statusRes = runApiSubprocess('POST', ['action' => 'mark_status', 'id' => $createdId], ['status' => 'Completed']);
assertApi("API Mark Status (Completed)", !empty($statusRes['success']) && ($statusRes['reminder']['reminder_status'] ?? '') === 'Completed');

// Test 7: Input Validation (Empty Title Rejected)
$emptyTitleRes = runApiSubprocess('POST', ['action' => 'add_reminder'], [
    'title'         => '',
    'reminder_date' => date('Y-m-d'),
    'reminder_time' => '09:00:00',
]);
assertApi("API Validation: Empty Title Rejected", empty($emptyTitleRes['success']) && !empty($emptyTitleRes['error']), "Error: " . ($emptyTitleRes['error'] ?? ''));

// Test 8: Input Validation (Invalid Date Rejected)
$badDateRes = runApiSubprocess('POST', ['action' => 'add_reminder'], [
    'title'         => 'Test',
    'reminder_date' => 'not-a-date',
    'reminder_time' => '09:00:00',
]);
assertApi("API Validation: Invalid Date Rejected", empty($badDateRes['success']), "Error: " . ($badDateRes['error'] ?? ''));

// Test 9: Check Due Endpoint
$dueRes = runApiSubprocess('GET', ['action' => 'check_due']);
assertApi("API Check Due Reminders", !empty($dueRes['success']) && isset($dueRes['due_reminders']) && isset($dueRes['missed_alerts']));

// Test 10: Delete Reminder
$delRes = runApiSubprocess('POST', ['action' => 'delete_reminder', 'id' => $createdId]);
assertApi("API Delete Reminder", !empty($delRes['success']));

echo "\n====================================================================\n";
echo "  API Test Summary: {$passed} PASSED, {$failed} FAILED (Total: " . ($passed + $failed) . ")\n";
echo "====================================================================\n";

if ($failed > 0) exit(1);
