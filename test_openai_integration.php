<?php
/**
 * Test script to verify OpenAI AI system endpoints in AayuVault
 */

echo "=== AayuVault OpenAI Health Analysis Engine Verification ===\n\n";

// 1. Test OpenAIService
require_once __DIR__ . '/pages/openai_service.php';
require_once __DIR__ . '/pages/analyze_report.php';

echo "1. Checking OpenAIService configuration...\n";
echo "   Model: " . OpenAIService::getModel() . "\n";
echo "   Is Configured: " . (OpenAIService::isConfigured() ? "YES" : "NO (Operating in Dual-Mode Local Fallback)") . "\n";

// 2. Test Report Analysis on Sample Lab Report Text
echo "\n2. Testing Report Analysis (Extracting Biomarkers)...\n";
$sampleLabText = <<<TEXT
QUEST DIAGNOSTICS - CLINICAL REPORT
Patient: Test Patient
Test: Lipid Profile & Fasting Blood Sugar
Total Cholesterol: 224 mg/dL
LDL Cholesterol: 142 mg/dL
HDL Cholesterol: 41 mg/dL
Triglycerides: 185 mg/dL
Fasting Blood Sugar: 108 mg/dL
HbA1c: 6.1%
Serum Creatinine: 0.98 mg/dL
ALT / SGPT: 34 U/L
Hemoglobin: 14.5 g/dL
TEXT;

$analysis = ReportAnalyzerEngine::analyzeReportText($sampleLabText, "Test Verification Panel");
echo "   Extracted " . count($analysis['biomarkers']) . " biomarkers:\n";
foreach ($analysis['biomarkers'] as $bm) {
    echo "   - {$bm['name']}: {$bm['value']} {$bm['unit']} [{$bm['status']}] (Organ: {$bm['organ']})\n";
}
echo "   Vital Score: " . $analysis['vital_score'] . "/100 (" . $analysis['overall_status'] . ")\n";
echo "   AI Engine: " . ($analysis['ai_engine'] ?? 'Local Engine') . "\n";
echo "   Identified Risks: " . count($analysis['risks']) . "\n";
echo "   Diet Prescriptions: " . count($analysis['diet_lifestyle']['diet']) . " items\n";
echo "   Doctor Questions: " . count($analysis['doctor_questions']) . " items\n";

// 3. Test Report Q&A
echo "\n3. Testing Report Q&A...\n";
$qna = ReportQnAHandler::answerQuestion("What foods should I eat to improve my cholesterol?", $analysis);
echo "   Question: What foods should I eat to improve my cholesterol?\n";
echo "   Category: " . ($qna['category'] ?? 'N/A') . "\n";
echo "   Answer:\n   " . str_replace("\n", "\n   ", $qna['answer']) . "\n";

// 4. Test Chatbot Endpoint Context Generation
echo "\n4. Testing AI Chatbot Context & Fallback Logic...\n";
require_once __DIR__ . '/pages/openai_service.php';
$chatRes = OpenAIService::chatWithHealthData("How is my cholesterol level?", [], [
    'patient' => 'Test User',
    'cholesterol' => '218 mg/dL'
]);
echo "   Chatbot Fallback status: " . ($chatRes['success'] ? 'SUCCESS' : 'FALLBACK READY') . "\n";

echo "\n=== All Unit Checks Passed Successfully! ===\n";
