<?php
/**
 * verify_appointment_system.php
 * Comprehensive automated verification test for AayuVault Appointment System
 */

define('AAYUVAULT_TEST_RUNNER', true);

require_once __DIR__ . '/pages/db.php';

$pdo = getDb();
$patientId = getLoggedInPatientId();

$testsPassed = 0;
$totalTests = 0;

function runTest($name, $callable) {
    global $testsPassed, $totalTests;
    $totalTests++;
    try {
        $result = $callable();
        if ($result === true) {
            echo " [PASS] Test {$totalTests}: {$name}\n";
            $testsPassed++;
        } else {
            echo " [FAIL] Test {$totalTests}: {$name} -> {$result}\n";
        }
    } catch (Throwable $e) {
        echo " [ERROR] Test {$totalTests}: {$name} -> " . $e->getMessage() . "\n";
    }
}

function callApi($action, $params = [], $method = 'GET') {
    $_GET = ($method === 'GET') ? array_merge(['action' => $action], $params) : ['action' => $action];
    $_POST = ($method === 'POST') ? array_merge(['action' => $action], $params) : [];
    $_SERVER['REQUEST_METHOD'] = $method;

    ob_start();
    include __DIR__ . '/pages/appointment.php';
    $output = ob_get_clean();

    // Parse JSON
    $start = strpos($output, '{');
    $end = strrpos($output, '}');
    if ($start !== false && $end !== false) {
        $jsonStr = substr($output, $start, $end - $start + 1);
        $data = json_decode($jsonStr, true);
        return [$data, $output];
    }
    return [null, $output];
}

echo "========================================================\n";
echo "   AAYUVAULT APPOINTMENT SYSTEM VERIFICATION SUITE       \n";
echo "========================================================\n\n";

// Test 1: Metrics
runTest('Metrics Endpoint (get_metrics)', function() {
    list($res, $raw) = callApi('metrics');
    if (!$res || !isset($res['blood_pressure']) || !isset($res['heart_rate'])) {
        return "Metrics missing fields. Raw: " . substr($raw, 0, 200);
    }
    return true;
});

// Test 2: Medical Summary Conditions
runTest('Medical Summary Conditions (conditions)', function() {
    list($res) = callApi('conditions');
    if (!$res || empty($res['conditions']) || !is_array($res['conditions'])) {
        return "Conditions empty or not array";
    }
    return true;
});

// Test 3: Health Context Tags
runTest('Health Context Tags (health_context)', function() {
    list($res) = callApi('health_context');
    if (!$res || empty($res['tags']) || !is_array($res['tags'])) {
        return "Health context tags missing or not array";
    }
    return true;
});

// Test 4: Condition Dropdown Options
runTest('Condition Dropdown Options (conditions_list)', function() {
    list($res) = callApi('conditions_list');
    if (!$res || empty($res['conditions']) || count($res['conditions']) < 4) {
        return "Condition options insufficient: " . count($res['conditions'] ?? []);
    }
    return true;
});

// Test 5: All Providers
runTest('Providers Endpoint (all providers with facility details)', function() {
    list($res) = callApi('providers');
    $providers = $res['providers'] ?? [];
    if (count($providers) < 5) {
        return "Expected at least 5 doctors, got " . count($providers);
    }
    // Verify each provider has facility info
    foreach ($providers as $p) {
        if (empty($p['name']) || empty($p['facility_name'])) {
            return "Provider missing name or facility_name: " . json_encode($p);
        }
    }
    return true;
});

// Test 6: Provider Search for user query 'Dr priya sharma'
runTest("Provider Search for 'Dr priya sharma'", function() {
    list($res) = callApi('providers', ['query' => 'Dr priya sharma']);
    $providers = $res['providers'] ?? [];
    if (count($providers) < 1) {
        return "Doctor 'Dr priya sharma' was not found";
    }
    if (stripos($providers[0]['name'], 'Priya Sharma') === false) {
        return "First match is not Priya Sharma: " . $providers[0]['name'];
    }
    if (empty($providers[0]['facility_name'])) {
        return "Facility name is missing for Dr. Priya Sharma";
    }
    return true;
});

// Test 7: Filter Options (Specialties & Zones)
runTest('Filter Options Endpoint (filter_options)', function() {
    list($res) = callApi('filter_options');
    if (!$res || empty($res['filters']) || empty($res['zones'])) {
        return "Filters or zones empty";
    }
    return true;
});

// Test 8: Time Slots Endpoint
runTest('Time Slots Endpoint (time_slots)', function() {
    list($res) = callApi('time_slots', ['date' => date('Y-m-d')]);
    if (!$res || empty($res['slots']) || count($res['slots']) < 6) {
        return "Time slots missing or insufficient";
    }
    return true;
});

// Test 9: Book Appointment & Verify Database Insertion
$bookedApptId = null;
$testDate = date('Y-m-d', strtotime('+3 days'));
$testTime = '10:30 AM';
$testCondition = 'Cardiovascular Review / Hypertension';

runTest('Book Appointment (POST book)', function() use (&$bookedApptId, $testDate, $testTime, $testCondition) {
    list($res) = callApi('book', [
        'doctor_id' => 1,
        'date'      => $testDate,
        'time'      => $testTime,
        'condition' => $testCondition,
    ], 'POST');

    if (!$res || !($res['success'] ?? false)) {
        return "Booking returned failure: " . ($res['message'] ?? 'unknown');
    }
    $bookedApptId = $res['appointment_id'] ?? null;
    if (!$bookedApptId) {
        return "Booking did not return appointment_id";
    }
    return true;
});

// Test 10: Verify Appointment in `appointments` DB table
runTest('Verify Appointment record in appointments table', function() use ($pdo, &$bookedApptId, $patientId, $testDate) {
    if (!$bookedApptId) return "No appointment id";
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = :id AND patient_id = :pid");
    $stmt->execute([':id' => $bookedApptId, ':pid' => $patientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return "Appointment row not found in database";
    }
    if ($row['doctor_name'] !== 'Dr. Priya Sharma') {
        return "Unexpected doctor_name: " . $row['doctor_name'];
    }
    if ($row['appointment_date'] !== $testDate) {
        return "Unexpected date: " . $row['appointment_date'];
    }
    return true;
});

// Test 11: Verify Automatic Reminder created in `patient_reminders` DB table
runTest('Verify Appointment Reminder created in patient_reminders', function() use ($pdo, $patientId, $testDate) {
    $stmt = $pdo->prepare("
        SELECT * FROM patient_reminders
        WHERE patient_id = :pid
          AND reminder_type = 'Doctor Appointment'
          AND reminder_date = :rdate
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':pid' => $patientId, ':rdate' => $testDate]);
    $row = $stmt->fetch();
    if (!$row) {
        return "No reminder created for booked appointment";
    }
    if (stripos($row['title'], 'Dr. Priya Sharma') === false) {
        return "Reminder title does not contain doctor name: " . $row['title'];
    }
    return true;
});

// Test 12: Upcoming Appointments reflects new appointment
runTest('Upcoming Appointments Endpoint reflects booking', function() {
    list($res) = callApi('upcoming');
    $appts = $res['appointments'] ?? [];
    if (empty($appts)) {
        return "Upcoming appointments is empty";
    }
    $found = false;
    foreach ($appts as $a) {
        if ($a['doctor_name'] === 'Dr. Priya Sharma') {
            $found = true;
            break;
        }
    }
    if (!$found) {
        return "Booked appointment with Dr. Priya Sharma not listed in upcoming";
    }
    return true;
});

// Test 13: File Integrity & Frontend Linking
runTest('Frontend files integrity & script tags', function() {
    $html = file_get_contents(__DIR__ . '/templates/appointment.html');
    if (strpos($html, 'appointment.js') === false) {
        return "appointment.js not linked in templates/appointment.html";
    }
    if (strpos($html, 'font-awesome') === false) {
        return "font-awesome not linked in templates/appointment.html";
    }
    $js = file_get_contents(__DIR__ . '/assets/js/appointment.js');
    if (strpos($js, 'APPOINTMENT_MODULE_LOADED') === false) {
        return "APPOINTMENT_MODULE_LOADED flag missing in appointment.js";
    }
    return true;
});

echo "\n========================================================\n";
echo "   TEST SUMMARY: {$testsPassed} / {$totalTests} PASSED\n";
echo "========================================================\n";

if ($testsPassed === $totalTests) {
    echo "🎉 ALL TESTS PASSED! Appointment & Reminders system fully operational.\n";
} else {
    echo "⚠️ Some tests failed. Please review the output above.\n";
}
