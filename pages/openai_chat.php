<?php
/**
 * ============================================================
 * AayuVault — OpenAI AI Health Assistant Chat Endpoint
 * pages/openai_chat.php
 *
 * Provides real-time conversational health coaching grounded in
 * the patient's actual medical records, lab findings, vitals,
 * and appointments using the OpenAI API.
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

require_once __DIR__ . '/openai_service.php';
require_once __DIR__ . '/analyze_report.php';

// Helper to gather patient health context for GPT grounding
function getPatientHealthContext(int $userId = 1): array {
    $context = [
        'patient_id' => $userId,
        'name' => 'Test User',
        'age' => 38,
        'vitals' => [
            'blood_pressure' => '120/80 mmHg',
            'heart_rate' => '72 bpm',
            'spo2' => '98%',
            'labs_status' => 'Attention Needed (1 High: Cholesterol)',
        ],
        'active_conditions' => [
            ['condition' => 'Borderline Dyslipidemia', 'category' => 'Cardiovascular', 'detail' => 'Total Cholesterol 218 mg/dL, LDL 138 mg/dL'],
            ['condition' => 'Pre-Diabetes', 'category' => 'Metabolic', 'detail' => 'HbA1c 5.9%, Fasting Glucose 104 mg/dL'],
            ['condition' => 'Vitamin D Insufficiency', 'category' => 'Nutritional', 'detail' => '25(OH)D ~ 22 ng/mL'],
        ],
        'recent_lab_reports' => [
            [
                'title' => 'Comprehensive Lipid Panel (Apr 02, 2025)',
                'summary' => 'Total Cholesterol: 218 mg/dL (High), LDL: 138 mg/dL (Borderline High), HDL: 44 mg/dL, Triglycerides: 178 mg/dL',
                'vital_score' => 74
            ],
            [
                'title' => 'Glycemic & HbA1c Panel (Mar 18, 2025)',
                'summary' => 'HbA1c: 5.9% (Pre-diabetic range 5.7%-6.4%), Fasting Glucose: 104 mg/dL',
                'vital_score' => 79
            ],
            [
                'title' => 'Complete Blood Count (Feb 28, 2025)',
                'summary' => 'Hemoglobin: 14.2 g/dL (Optimal), WBC: 6,800 /µL, Platelets: 245k',
                'vital_score' => 93
            ],
            [
                'title' => 'Comprehensive Metabolic & Liver Panel (Jan 15, 2025)',
                'summary' => 'ALT/SGPT: 38 U/L, Creatinine: 0.95 mg/dL, eGFR: >90, Bilirubin: 0.8 mg/dL',
                'vital_score' => 88
            ]
        ],
        'upcoming_appointments' => [
            ['doctor' => 'Dr. Priya Sharma', 'specialty' => 'Cardiology', 'time' => 'Thursday, 10:00 AM'],
            ['doctor' => 'Dr. Rajeev Mehta', 'specialty' => 'Endocrinology', 'time' => 'Next Tuesday, 3:30 PM']
        ]
    ];

    // Attempt to enrich with live DB records if available
    try {
        $pdo = getDB();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM metrics WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1");
            $stmt->execute([$userId]);
            $dbMetrics = $stmt->fetch();
            if ($dbMetrics) {
                $context['vitals']['blood_pressure'] = $dbMetrics['blood_pressure'] . ' mmHg';
                $context['vitals']['heart_rate'] = $dbMetrics['heart_rate'] . ' bpm';
                $context['vitals']['spo2'] = $dbMetrics['spo2'] . '%';
            }

            $stmt2 = $pdo->prepare("SELECT title, subtitle, date_label FROM medical_records WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $stmt2->execute([$userId]);
            $dbRecords = $stmt2->fetchAll();
            if (!empty($dbRecords)) {
                $context['recent_lab_reports'] = array_map(function($r) {
                    return [
                        'title' => $r['title'] . ' (' . $r['date_label'] . ')',
                        'summary' => $r['subtitle']
                    ];
                }, $dbRecords);
            }
        }
    } catch (Throwable $e) {
        // Fall back to pre-populated context
    }

    return $context;
}

try {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true) ?: $_POST;

    $message = trim($body['message'] ?? $body['text'] ?? $body['query'] ?? '');
    $history = is_array($body['history'] ?? null) ? $body['history'] : [];
    $userId = intval($body['user_id'] ?? 1);

    // Optional user runtime API key override
    if (!empty($body['api_key'])) {
        OpenAIService::setApiKey($body['api_key']);
    }

    if (empty($message)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Message cannot be empty.'
        ]);
        exit;
    }

    $healthContext = getPatientHealthContext($userId);

    // 1. Try OpenAI API
    if (OpenAIService::isConfigured()) {
        $aiResponse = OpenAIService::chatWithHealthData($message, $history, $healthContext);
        if ($aiResponse['success']) {
            echo json_encode([
                'success' => true,
                'reply' => $aiResponse['reply'],
                'engine' => $aiResponse['ai_engine'],
                'is_openai' => true,
                'model' => OpenAIService::getModel()
            ]);
            exit;
        }
    }

    // 2. Local Fallback Knowledge Engine (if OpenAI key not present or offline)
    $lower = strtolower($message);
    $reply = "";

    if (str_contains($lower, 'cholesterol') || str_contains($lower, 'lipid') || str_contains($lower, 'ldl') || str_contains($lower, 'heart')) {
        $reply = "📊 **Cardiovascular Summary:**\n"
               . "Your latest Lipid Panel (*Apr 02, 2025*) shows **Total Cholesterol at 218 mg/dL** (borderline high) and **LDL at 138 mg/dL**.\n\n"
               . "💡 **Key Recommendations:**\n"
               . "• Increase soluble fiber (oats, chia seeds, lentils, apples) by 10g/day.\n"
               . "• Swap saturated fats with extra virgin olive oil.\n"
               . "• 30 minutes of daily aerobic exercise helps boost HDL clearance.\n"
               . "• You have an upcoming appointment with **Dr. Priya Sharma (Cardiology)** on Thursday at 10:00 AM.";
    } elseif (str_contains($lower, 'sugar') || str_contains($lower, 'glucose') || str_contains($lower, 'diabetes') || str_contains($lower, 'hba1c')) {
        $reply = "🩸 **Glycemic & Diabetes Profile:**\n"
               . "Your HbA1c is **5.9%**, placing you in the **pre-diabetic zone** (standard pre-diabetes range is 5.7% – 6.4%). Fasting glucose was **104 mg/dL**.\n\n"
               . "💡 **Action Steps:**\n"
               . "• Take a 10–15 minute walk immediately following lunch and dinner.\n"
               . "• Reduce refined sugars, white bread, and sweetened beverages.\n"
               . "• Focus on high-protein, fiber-rich meals to blunt glucose spikes.\n"
               . "• Consider repeating your HbA1c in 90 days to verify reversal.";
    } elseif (str_contains($lower, 'bp') || str_contains($lower, 'pressure') || str_contains($lower, 'vitals') || str_contains($lower, 'pulse')) {
        $reply = "🫀 **Current Vitals Status:**\n"
               . "• **Blood Pressure:** 120/80 mmHg (Optimal)\n"
               . "• **Heart Rate:** 72 bpm (Resting normal)\n"
               . "• **SpO2:** 98% (Healthy arterial oxygen saturation)\n\n"
               . "All vital signs are currently operating within ideal physiological parameters.";
    } elseif (str_contains($lower, 'appointment') || str_contains($lower, 'doctor') || str_contains($lower, 'book')) {
        $reply = "📅 **Upcoming Appointments:**\n"
               . "1. **Dr. Priya Sharma** (Cardiology) — Thursday, 10:00 AM\n"
               . "2. **Dr. Rajeev Mehta** (Endocrinology) — Next Tuesday, 3:30 PM\n\n"
               . "You can book new consultations or reschedule by visiting the **Appointments** tab.";
    } elseif (str_contains($lower, 'report') || str_contains($lower, 'record') || str_contains($lower, 'test') || str_contains($lower, 'lab')) {
        $reply = "📑 **Your Health Records Overview:**\n"
               . "You have **4 verified clinical reports** in your AayuVault:\n"
               . "1. **Comprehensive Lipid Panel** (Apr 02, 2025) — Vital Score 74/100 (High Cholesterol)\n"
               . "2. **Glycemic & HbA1c Panel** (Mar 18, 2025) — Vital Score 79/100 (Pre-diabetic)\n"
               . "3. **Complete Blood Count (CBC)** (Feb 28, 2025) — Vital Score 93/100 (Optimal)\n"
               . "4. **Metabolic & Liver Panel** (Jan 15, 2025) — Vital Score 88/100 (Optimal)\n\n"
               . "Click on any record or open **AI Analytics** for longitudinal trend graphs!";
    } elseif (str_contains($lower, 'diet') || str_contains($lower, 'food') || str_contains($lower, 'eat') || str_contains($lower, 'nutrition')) {
        $reply = "🥗 **Tailored Dietary Guidance:**\n"
               . "Based on your combined lipid and glycemic parameters:\n"
               . "• **Add:** Leafy greens, walnuts, flaxseeds, legumes, fatty fish, oats, avocados.\n"
               . "• **Limit:** Deep-fried snacks, pastries, sugary sodas, and processed red meat.\n"
               . "• **Hydration:** Aim for 2.5L to 3.0L of water daily.";
    } else {
        $reply = "Hello! I am your **AayuVault AI Health Assistant**.\n\n"
               . "I can analyze your lab reports, track biomarker trends (cholesterol, blood glucose, HbA1c, CBC, liver/kidney markers), and give personalized wellness insights grounded in your records.\n\n"
               . "✨ *Tip: Try asking:*\n"
               . "• *\"How is my cholesterol doing?\"*\n"
               . "• *\"Explain my HbA1c and pre-diabetes risk.\"*\n"
               . "• *\"What diet should I follow for my lab results?\"*\n"
               . "• *\"What are my upcoming appointments?\"*";
    }

    echo json_encode([
        'success' => true,
        'reply' => $reply,
        'engine' => 'AayuVault Clinical Knowledge Engine (Local Fallback)',
        'is_openai' => false,
        'note' => 'To enable real-time OpenAI GPT reasoning, add your OPENAI_API_KEY to .env or settings.'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Chatbot error: ' . $e->getMessage()
    ]);
}
