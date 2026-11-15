<?php
/**
 * ============================================================
 * AayuVault — Report Upload & Real-Time AI Analyzer Endpoint
 * pages/upload.php
 *
 * Handles file uploads (PDF, JPG, PNG, TXT), performs text
 * extraction, runs AI clinical parameter analysis, and returns
 * structured JSON with full biomarker breakdown.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/analyze_report.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST request expected.']);
        exit;
    }

    $uploadedFile = $_FILES['report'] ?? $_FILES['file'] ?? null;
    $rawText = $_POST['text'] ?? '';
    $filename = 'Uploaded Report';

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $extractedContent = $rawText;

    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $filename = basename($uploadedFile['name']);
        $fileTmp = $uploadedFile['tmp_name'];
        $fileSize = $uploadedFile['size'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'csv'];
        if (!in_array($fileExt, $allowedExts)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file format. Please upload PDF, JPG, PNG, or TXT.'
            ]);
            exit;
        }

        // Target save destination
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        $targetPath = $uploadDir . $safeName;
        @move_uploaded_file($fileTmp, $targetPath);

        // If text or CSV, read content directly
        if ($fileExt === 'txt' || $fileExt === 'csv') {
            $extractedContent .= "\n" . @file_get_contents($targetPath);
        } elseif ($fileExt === 'pdf') {
            // Extract text from PDF document
            $pdfText = ReportAnalyzerEngine::extractTextFromPdf($targetPath);
            if (!empty($pdfText)) {
                $extractedContent .= "\n" . $pdfText;
            } else {
                $extractedContent .= "\nDocument: " . $filename;
            }
        } else {
            // For images and other files
            $extractedContent .= "\nDocument: " . $filename;
        }
    }

    // Run AI Medical Analysis Engine
    $analysis = ReportAnalyzerEngine::analyzeReportText($extractedContent, $filename);

    // If Database is connected, insert record into medical_records and cache analysis
    $pdo = getDB();
    $newRecordId = rand(100, 999);
    if ($pdo) {
        try {
            $reportTypeLower = strtolower($analysis['report_type'] ?? 'lab');
            $recType = (strpos($reportTypeLower, 'prescription') !== false) ? 'prescription' : 'lab';
            $subtitle = "AI Analyzed • Vital Score: " . ($analysis['vital_score'] ?? 80) . "/100 (" . ($analysis['overall_status'] ?? 'Normal') . ")";

            $stmt = $pdo->prepare("
                INSERT INTO medical_records (user_id, date_label, title, subtitle, type, facility, has_pdf, pdf_path, raw_text, ai_analyzed)
                VALUES (1, :date_label, :title, :subtitle, :type, :facility, 1, :pdf_path, :raw_text, 1)
            ");
            $stmt->execute([
                ':date_label' => date('M d, Y'),
                ':title' => $analysis['report_title'] ?? $filename,
                ':subtitle' => $subtitle,
                ':type' => $recType,
                ':facility' => $analysis['facility'] ?? 'AayuVault Clinical Diagnostics',
                ':pdf_path' => 'uploads/' . ($safeName ?? $filename),
                ':raw_text' => $extractedContent,
            ]);
            $newRecordId = (int)$pdo->lastInsertId();
            $analysis['record_id'] = $newRecordId;

            // Cache the parsed AI analysis in the report_analyses table
            ReportAnalyzerEngine::saveReportAnalysis($pdo, $newRecordId, 1, $analysis);
        } catch (Throwable $e) {
            // Database insert failed, proceed with in-memory analysis
            error_log("Database insert failed during upload: " . $e->getMessage());
            $analysis['record_id'] = $newRecordId;
        }
    } else {
        $analysis['record_id'] = $newRecordId;
    }

    echo json_encode([
        'success' => true,
        'message' => "Successfully analyzed \"{$filename}\"",
        'filename' => $filename,
        'analysis' => $analysis,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload and analysis error: ' . $e->getMessage()
    ]);
}
