<?php
$src = __DIR__;
$dst = 'C:/xampp/htdocs/AayuVault';

$files = [
    'assets/css/appointment.css',
    'assets/css/records.css',
    'assets/css/remainder.css',
    'assets/css/reminders_alerts.css',
    'assets/js/appointment.js',
    'assets/js/records.js',
    'assets/js/remainder.js',
    'assets/js/reminders_alerts.js',
    'database/aayuvault_local.sqlite',
    'database/clinic_db_schema.sql',
    'pages/analyze_report.php',
    'pages/appointment.php',
    'pages/db.php',
    'pages/patient_reminders_api.php',
    'pages/records.php',
    'pages/reminders_api.php',
    'templates/app.js',
    'templates/appointment.html',
    'templates/dashboard.html',
    'templates/records.html',
    'templates/analytics.html',
    'templates/remainder.html'
];

foreach ($files as $rel) {
    $sourceFile = $src . '/' . $rel;
    $destFile = $dst . '/' . $rel;
    $destDir = dirname($destFile);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }
    if (file_exists($sourceFile)) {
        if (copy($sourceFile, $destFile)) {
            echo "Copied successfully: {$rel}\n";
        } else {
            echo "Failed to copy: {$rel}\n";
        }
    } else {
        echo "Source file does not exist: {$rel}\n";
    }
}

echo "Sync completed!\n";
