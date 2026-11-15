<?php
/**
 * cron_scheduler.php — AayuVault Reliable Server-Side Scheduler Runner
 *
 * Can be executed via:
 * 1. CLI / Windows Task Scheduler / Linux Cron:
 *    php cron_scheduler.php
 * 2. HTTP GET with secret token (for web cron services):
 *    http://localhost/AayuVault/cron_scheduler.php?secret=aayuvault_cron_secret
 *
 * Behavior:
 * - Generates daily medication dose records from active patient schedules
 * - Evaluates pending/snoozed doses past grace period to transition to 'missed'
 * - Generates advance appointment notifications (today, 24h, 1h)
 * - Flags past appointments needing status confirmation
 * - Prevents duplicate alerts via database unique constraints
 */

if (php_sapi_name() !== 'cli') {
    // Basic secret guard for HTTP access
    $envSecret = getenv('CRON_SECRET') ?: 'aayuvault_cron_secret';
    $reqSecret = $_GET['secret'] ?? '';
    if ($reqSecret !== $envSecret) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized scheduler execution. Pass ?secret=...']);
        exit;
    }
}

require_once __DIR__ . '/pages/db.php';
require_once __DIR__ . '/pages/scheduler_service.php';

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$start = microtime(true);
$scheduler = new SchedulerService(getDb());
$results   = $scheduler->runCycle();
$elapsed   = round((microtime(true) - $start) * 1000, 2);

$results['execution_time_ms'] = $elapsed;

if (php_sapi_name() === 'cli') {
    echo "========================================================\n";
    echo "  AayuVault Reminder & Appointment Scheduler Cycle\n";
    echo "========================================================\n";
    echo "Time:                  " . $results['timestamp'] . "\n";
    echo "Doses Generated:       " . $results['doses_generated'] . "\n";
    echo "Doses Marked Missed:   " . $results['doses_marked_missed'] . "\n";
    echo "Appointment Alerts:    " . $results['appointment_alerts'] . "\n";
    echo "Past Appts Flagged:    " . $results['past_appts_flagged'] . "\n";
    echo "Execution Time:        " . $elapsed . " ms\n";
    echo "Status:                " . ($results['success'] ? "SUCCESS" : "FAILED") . "\n";
    echo "========================================================\n";
} else {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

exit($results['success'] ? 0 : 1);
