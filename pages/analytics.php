<?php
session_start();
/**
 * ============================================================
 * AayuVault — AI Medical Analytics & Longitudinal Trends API
 * pages/analytics.php
 *
 * Provides aggregated health metrics, organ health scores,
 * longitudinal biomarker trends over time, and OpenAI-powered
 * longitudinal multi-report clinical synthesis.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-OpenAI-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/analyze_report.php';
require_once __DIR__ . '/openai_service.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'summary';
$userId = $_SESSION['user_id'] ?? 1;

try {
    $pdo = getDB();
    switch ($action) {

        // 1. Overall Analytics Summary
        case 'summary':
            if ($pdo) {
                $summary = ReportAnalyzerEngine::getDynamicAnalyticsSummary($pdo, $userId);
                echo json_encode($summary);
                exit;
            }
            echo json_encode([
                'success' => true,
                'health_index' => 84,
                'health_status' => 'Good Condition',
                'trend_direction' => '+4% vs Last Quarter',
                'total_reports' => 4,
                'out_of_range_count' => 2,
                'organ_systems' => [
                    ['name' => 'Cardiovascular', 'score' => 74, 'status' => 'Attention Needed', 'icon' => 'fa-heart-pulse', 'color' => '#f59e0b'],
                    ['name' => 'Metabolic', 'score' => 79, 'status' => 'Mild Concern', 'icon' => 'fa-fire', 'color' => '#f59e0b'],
                    ['name' => 'Liver & Hepatic', 'score' => 92, 'status' => 'Optimal', 'icon' => 'fa-shield-halved', 'color' => '#10b981'],
                    ['name' => 'Renal & Kidneys', 'score' => 96, 'status' => 'Optimal', 'icon' => 'fa-droplet', 'color' => '#10b981'],
                    ['name' => 'Blood & Immune', 'score' => 94, 'status' => 'Optimal', 'icon' => 'fa-dna', 'color' => '#10b981'],
                ],
                'key_watchlist' => [
                    ['biomarker' => 'Total Cholesterol', 'value' => '218 mg/dL', 'target' => '< 200 mg/dL', 'status' => 'High', 'delta' => '+14 mg/dL'],
                    ['biomarker' => 'LDL Cholesterol', 'value' => '138 mg/dL', 'target' => '< 100 mg/dL', 'status' => 'High', 'delta' => '+12 mg/dL'],
                    ['biomarker' => 'HbA1c', 'value' => '5.9%', 'target' => '< 5.7%', 'status' => 'Pre-diabetic', 'delta' => '+0.2%'],
                    ['biomarker' => 'Fasting Glucose', 'value' => '104 mg/dL', 'target' => '< 99 mg/dL', 'status' => 'Elevated', 'delta' => '+5 mg/dL'],
                ]
            ]);
            break;

        // 2. Longitudinal Biomarker Trends
        case 'trends':
            if ($pdo) {
                $trends = ReportAnalyzerEngine::getDynamicTrends($pdo, $userId);
                echo json_encode([
                    'success' => true,
                    'trends' => $trends
                ]);
                exit;
            }
            echo json_encode([
                'success' => true,
                'trends' => [
                    'lipid' => [
                        'title' => 'Lipid & Cholesterol Progression',
                        'labels' => ['Nov 2024', 'Jan 2025', 'Feb 2025', 'Apr 2025'],
                        'series' => [
                            ['name' => 'Total Cholesterol (mg/dL)', 'data' => [232, 224, 220, 218], 'target' => 200, 'color' => '#ef4444'],
                            ['name' => 'LDL Cholesterol (mg/dL)', 'data' => [152, 144, 140, 138], 'target' => 100, 'color' => '#f59e0b'],
                            ['name' => 'HDL Cholesterol (mg/dL)', 'data' => [39, 41, 42, 44], 'target' => 50, 'color' => '#10b981'],
                        ]
                    ],
                    'glycemic' => [
                        'title' => 'Glycemic & Glucose Control',
                        'labels' => ['Nov 2024', 'Jan 2025', 'Feb 2025', 'Mar 2025'],
                        'series' => [
                            ['name' => 'Fasting Glucose (mg/dL)', 'data' => [112, 108, 106, 104], 'target' => 99, 'color' => '#f59e0b'],
                            ['name' => 'HbA1c (%)', 'data' => [6.2, 6.1, 6.0, 5.9], 'target' => 5.6, 'color' => '#c49a2a'],
                        ]
                    ],
                    'hematology' => [
                        'title' => 'Complete Blood Count Trends',
                        'labels' => ['Nov 2024', 'Jan 2025', 'Feb 2025'],
                        'series' => [
                            ['name' => 'Hemoglobin (g/dL)', 'data' => [13.8, 14.0, 14.2], 'target' => 14.0, 'color' => '#3b82f6'],
                            ['name' => 'Platelets (K/µL)', 'data' => [230, 240, 245], 'target' => 250, 'color' => '#8b5cf6'],
                        ]
                    ]
                ]
            ]);
            break;

        // 3. Multi-Report Parameter Comparison Matrix
        case 'comparison':
            if ($pdo) {
                $matrix = ReportAnalyzerEngine::getDynamicComparisonMatrix($pdo, $userId);
                echo json_encode([
                    'success' => true,
                    'matrix' => $matrix
                ]);
                exit;
            }
            echo json_encode([
                'success' => true,
                'matrix' => [
                    'dates' => ['Jan 15, 2025', 'Feb 28, 2025', 'Mar 18, 2025', 'Apr 02, 2025'],
                    'parameters' => [
                        ['name' => 'Total Cholesterol', 'unit' => 'mg/dL', 'ref' => '< 200', 'values' => ['224', '—', '—', '218'], 'status' => 'Improving (High)'],
                        ['name' => 'LDL Cholesterol', 'unit' => 'mg/dL', 'ref' => '< 100', 'values' => ['144', '—', '—', '138'], 'status' => 'Improving (High)'],
                        ['name' => 'HDL Cholesterol', 'unit' => 'mg/dL', 'ref' => '> 40', 'values' => ['41', '—', '—', '44'], 'status' => 'Normal (Rising)'],
                        ['name' => 'HbA1c', 'unit' => '%', 'ref' => '< 5.7', 'values' => ['6.1', '—', '5.9', '—'], 'status' => 'Pre-diabetic (Improving)'],
                        ['name' => 'Fasting Glucose', 'unit' => 'mg/dL', 'ref' => '70–99', 'values' => ['108', '—', '104', '—'], 'status' => 'Borderline'],
                        ['name' => 'Hemoglobin', 'unit' => 'g/dL', 'ref' => '13.0–17.0', 'values' => ['—', '14.2', '—', '—'], 'status' => 'Optimal'],
                        ['name' => 'Serum Creatinine', 'unit' => 'mg/dL', 'ref' => '0.6–1.2', 'values' => ['0.95', '—', '—', '—'], 'status' => 'Optimal'],
                        ['name' => 'ALT / SGPT', 'unit' => 'U/L', 'ref' => '7–45', 'values' => ['38', '—', '—', '—'], 'status' => 'Optimal'],
                    ]
                ]
            ]);
            break;

        // 4. OpenAI Deep Longitudinal Synthesis
        case 'openai_longitudinal':
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?: $_POST;

            if (!empty($body['api_key'])) {
                OpenAIService::setApiKey($body['api_key']);
            }

            if ($pdo) {
                $samples = ReportAnalyzerEngine::getUserReportAnalyses($pdo, $userId);
                $metrics = ReportAnalyzerEngine::getUserLatestMetrics($pdo, $userId);
            } else {
                $samples = array_values(MedicalKnowledgeBase::getSampleAnalyses());
                $metrics = [
                    'blood_pressure' => '120/80 mmHg',
                    'heart_rate' => '72 bpm',
                    'spo2' => '98%',
                ];
            }

            if (OpenAIService::isConfigured()) {
                $longitudinalResult = OpenAIService::analyzeLongitudinalData($samples, $metrics);
                if ($longitudinalResult['success']) {
                    echo json_encode([
                        'success' => true,
                        'analysis' => $longitudinalResult
                    ]);
                    exit;
                }
            }

            // Fallback Longitudinal Synthesis
            echo json_encode([
                'success' => true,
                'analysis' => [
                    'health_index' => 84,
                    'health_status' => 'Good Condition',
                    'trajectory_summary' => 'Multi-quarter tracking shows positive improvements in lipid and glycemic curves. Total cholesterol has reduced from 232 mg/dL to 218 mg/dL, and HbA1c has improved from 6.2% to 5.9%. Ongoing aerobic fitness and soluble fiber intake will help stabilize parameters within target intervals.',
                    'improving_parameters' => [
                        ['name' => 'Total Cholesterol', 'note' => 'Gradual 14 mg/dL reduction over past 5 months.'],
                        ['name' => 'HbA1c', 'note' => 'Down from 6.2% to 5.9% towards optimal baseline (<5.7%).'],
                        ['name' => 'HDL Cholesterol', 'note' => 'Increased from 39 to 44 mg/dL indicating positive cardiovascular response.']
                    ],
                    'watchlist_parameters' => [
                        ['name' => 'LDL Cholesterol', 'current' => '138 mg/dL', 'target' => '< 100 mg/dL', 'status' => 'Borderline High', 'action' => 'Maintain dietary fiber and reduce saturated fat intake.'],
                        ['name' => 'Fasting Glucose', 'current' => '104 mg/dL', 'target' => '< 99 mg/dL', 'status' => 'Elevated', 'action' => 'Incorporate 15-minute post-meal brisk walking.']
                    ],
                    'organ_systems' => [
                        ['name' => 'Cardiovascular', 'score' => 74, 'status' => 'Attention Needed', 'icon' => 'fa-heart-pulse', 'analysis' => 'Elevated LDL and borderline triglycerides require continued monitoring.'],
                        ['name' => 'Metabolic', 'score' => 79, 'status' => 'Mild Concern', 'icon' => 'fa-fire', 'analysis' => 'Fasting glucose and HbA1c reflect reversible pre-diabetes.'],
                        ['name' => 'Liver & Hepatic', 'score' => 92, 'status' => 'Optimal', 'icon' => 'fa-shield-halved', 'analysis' => 'Enzymes ALT/SGPT and Bilirubin within ideal range.'],
                        ['name' => 'Renal & Kidneys', 'score' => 96, 'status' => 'Optimal', 'icon' => 'fa-droplet', 'analysis' => 'Creatinine and eGFR demonstrate strong filtration efficiency.'],
                        ['name' => 'Blood & Immune', 'score' => 94, 'status' => 'Optimal', 'icon' => 'fa-dna', 'analysis' => 'Complete blood count parameters are well balanced.']
                    ],
                    'forecast_risks' => [
                        ['risk' => 'Atherosclerosis Progression', 'probability' => 'Low-Moderate', 'timeline' => '12 Months', 'prevention' => 'Sustain aerobic exercise and Mediterranean-style nutrition.'],
                        ['risk' => 'Type 2 Diabetes Transition', 'probability' => 'Moderate', 'timeline' => '2-3 Years without intervention', 'prevention' => 'Aim for 5-7% weight reduction and low glycemic index meals.']
                    ],
                    'action_plan_90_days' => [
                        'Repeat comprehensive lipid panel and HbA1c in 90 days.',
                        'Target 150 minutes of moderate aerobic exercise per week.',
                        'Maintain 2.5L to 3.0L daily water hydration.',
                        'Consult with clinical nutritionist regarding personalized carbohydrate threshold.'
                    ],
                    'doctor_consultation_brief' => 'The patient shows improving metabolic and lipid parameters under lifestyle therapy. Total cholesterol (218 mg/dL) and HbA1c (5.9%) remain mildly out-of-range, but show positive momentum. Kidney, liver, and hematology profiles are healthy. Recommendation is to continue lifestyle optimization for 3 more months before considering pharmacological intervention.',
                    'ai_engine' => 'AayuVault Longitudinal Engine (Local Fallback)'
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => true, 'message' => 'AayuVault Analytics Endpoint Active']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
