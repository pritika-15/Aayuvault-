<?php
/**
 * ============================================================
 * AayuVault — OpenAI API Service Engine
 * pages/openai_service.php
 *
 * Core service class interfacing with OpenAI's API (gpt-4o,
 * gpt-4o-mini, etc.) for clinical report parsing, longitudinal
 * health analytics, biomarker extraction, and interactive health Q&A.
 * Includes graceful dual-mode fallback when offline / no key.
 * ============================================================
 */

class OpenAIService {
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    const MODELS_ENDPOINT = 'https://api.openai.com/v1/models';

    private static ?string $cachedKey = null;
    private static ?string $cachedModel = null;

    /**
     * Load environment variables from .env file if present
     */
    private static function loadEnv(): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if (!isset($_ENV[$k])) {
                        $_ENV[$k] = $v;
                        putenv("$k=$v");
                    }
                }
            }
        }
    }

    /**
     * Retrieve OpenAI API Key from runtime settings, .env, environment or config file
     */
    public static function getApiKey(): ?string {
        if (self::$cachedKey !== null && self::$cachedKey !== '') {
            return self::$cachedKey;
        }

        self::loadEnv();

        // 1. Check HTTP header from request (user-supplied key)
        if (!empty($_SERVER['HTTP_X_OPENAI_KEY'])) {
            return trim($_SERVER['HTTP_X_OPENAI_KEY']);
        }

        // 2. Check PHP $_ENV / getenv
        $key = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null;
        if ($key && trim($key) !== '') {
            self::$cachedKey = trim($key);
            return self::$cachedKey;
        }

        // 3. Check config/openai_config.json
        $cfgFile = __DIR__ . '/../config/openai_config.json';
        if (file_exists($cfgFile)) {
            $data = json_decode(@file_get_contents($cfgFile), true);
            if (!empty($data['api_key'])) {
                self::$cachedKey = trim($data['api_key']);
                return self::$cachedKey;
            }
        }

        return null;
    }

    /**
     * Set/override API key at runtime
     */
    public static function setApiKey(string $key): void {
        self::$cachedKey = trim($key);
    }

    /**
     * Retrieve OpenAI Model name (defaults to gpt-4o-mini for fast & cost-efficient clinical reasoning)
     */
    public static function getModel(): string {
        if (self::$cachedModel !== null) {
            return self::$cachedModel;
        }

        self::loadEnv();
        $model = $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
        self::$cachedModel = trim($model);
        return self::$cachedModel;
    }

    /**
     * Check if OpenAI API is configured with an active key
     */
    public static function isConfigured(): bool {
        $key = self::getApiKey();
        return !empty($key) && strlen($key) > 10;
    }

    /**
     * Test connection to OpenAI API with given or saved key
     */
    public static function testConnection(?string $apiKey = null): array {
        $key = $apiKey ?: self::getApiKey();
        if (empty($key)) {
            return [
                'success' => false,
                'message' => 'No OpenAI API key provided. Add your key to .env or settings.'
            ];
        }

        $payload = [
            'model' => self::getModel(),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a medical data assistant.'],
                ['role' => 'user', 'content' => 'Respond with the word "OK".']
            ],
            'max_tokens' => 5,
            'temperature' => 0.0,
        ];

        $res = self::makeHttpRequest(self::API_ENDPOINT, $payload, $key);
        if ($res['success']) {
            return [
                'success' => true,
                'message' => 'Successfully connected to OpenAI API (' . self::getModel() . ')!',
                'model' => self::getModel()
            ];
        }

        return [
            'success' => false,
            'message' => 'OpenAI API test failed: ' . ($res['error'] ?? 'Unknown error')
        ];
    }

    /**
     * Perform HTTP POST request to OpenAI
     */
    private static function makeHttpRequest(string $url, array $payload, ?string $apiKey = null): array {
        $key = $apiKey ?: self::getApiKey();
        if (empty($key)) {
            return ['success' => false, 'error' => 'Missing OpenAI API Key'];
        }

        $jsonPayload = json_encode($payload);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ];

        // If cURL is available
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 40);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // compatibility for local environments

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return ['success' => false, 'error' => 'cURL Error: ' . $curlError];
            }

            $decoded = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['choices'][0]['message']['content'])) {
                return ['success' => true, 'data' => $decoded, 'content' => $decoded['choices'][0]['message']['content']];
            }

            $errMsg = $decoded['error']['message'] ?? "HTTP error status {$httpCode}";
            return ['success' => false, 'error' => $errMsg, 'raw' => $response];
        }

        // Fallback to file_get_contents stream context
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => $jsonPayload,
                'timeout' => 40,
                'ignore_errors' => true,
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'error' => 'Stream connection to OpenAI failed.'];
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            return ['success' => true, 'data' => $decoded, 'content' => $decoded['choices'][0]['message']['content']];
        }

        $errMsg = $decoded['error']['message'] ?? 'Unknown OpenAI API error';
        return ['success' => false, 'error' => $errMsg];
    }

    /**
     * ── ANALYZE MEDICAL REPORT VIA OPENAI API ──────────────────
     * Extracts all clinical parameters, reference ranges, organ impacts,
     * vital score (0-100), clinical risks, and lifestyle prescriptions.
     */
    public static function analyzeMedicalReport(string $rawText, string $reportTitle = 'Medical Lab Report', array $patientContext = []): array {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => 'OpenAI API key is not configured.'
            ];
        }

        $systemPrompt = <<<PROMPT
You are an expert Clinical Pathologist and AI Medical Diagnostics Engine for "AayuVault".
Your task is to thoroughly analyze the provided clinical document, lab report, or diagnostic test text.

You MUST extract and generate a comprehensive, clinically accurate JSON object with the following schema:
{
  "report_title": "Clean, descriptive name of the test or report",
  "report_type": "e.g. Lipid Profile, Diabetes Panel, Complete Blood Count, Liver Function, Kidney Function, Thyroid Profile, etc.",
  "facility": "Diagnostic Center / Lab Name (or 'AayuVault Clinical Diagnostics')",
  "report_date": "Date found in text or formatted as 'M d, Y'",
  "patient_name": "Patient name or 'Patient'",
  "vital_score": 82, // An integer 0-100 indicating the overall health vitality score derived from out-of-range biomarkers
  "overall_status": "Optimal Health" | "Attention Needed" | "Action Required",
  "summary_text": "A clear, empathetic, 2-3 sentence executive clinical interpretation explaining the main findings in patient-friendly terms.",
  "biomarkers": [
    {
      "key": "machine_readable_key_e.g_total_cholesterol",
      "name": "Full Clinical Parameter Name",
      "value": 218.0, // Numeric float or integer
      "unit": "e.g. mg/dL, g/dL, %, /µL, U/L",
      "ref_min": 125.0, // Standard clinical reference minimum
      "ref_max": 200.0, // Standard clinical reference maximum
      "status": "Optimal" | "Normal" | "Borderline" | "Borderline High" | "High" | "Low" | "Critical High" | "Critical Low",
      "severity": "normal" | "warning" | "danger" | "info",
      "gauge_pct": 72, // Number 0-100 representing position on health gauge slider
      "category": "e.g. Lipid Profile, Glycemic, Hematology, Hepatic, Renal, Thyroid, Vitamins",
      "organ": "Cardiovascular" | "Metabolic" | "Liver" | "Kidneys" | "Blood / Immune",
      "description": "Short clinical note on what this parameter's current level means for the patient's physiology."
    }
  ],
  "risks": [
    {
      "title": "e.g. Atherosclerosis Risk, Glycemic Escalation",
      "level": "Low" | "Moderate" | "High",
      "desc": "Explanation of clinical risk factors based on the identified out-of-range markers."
    }
  ],
  "organ_impact": [
    { "system": "Cardiovascular", "status": "Optimal"|"Mild Concern"|"Attention Needed", "score": 85, "icon": "fa-heart-pulse" },
    { "system": "Metabolic", "status": "Optimal"|"Mild Concern"|"Attention Needed", "score": 80, "icon": "fa-fire" },
    { "system": "Liver", "status": "Optimal"|"Mild Concern"|"Attention Needed", "score": 92, "icon": "fa-shield-halved" },
    { "system": "Kidneys", "status": "Optimal"|"Mild Concern"|"Attention Needed", "score": 95, "icon": "fa-droplet" },
    { "system": "Blood / Immune", "status": "Optimal"|"Mild Concern"|"Attention Needed", "score": 90, "icon": "fa-dna" }
  ],
  "diet_lifestyle": {
    "diet": ["Bullet point 1 actionable nutritional advice", "Bullet point 2..."],
    "lifestyle": ["Physical exercise / sleep / hydration advice..."],
    "precautions": ["Key precautions or red flag symptoms to watch for..."]
  },
  "doctor_questions": [
    "Specific, smart question the patient should ask their physician during their next visit...",
    "Another smart doctor question..."
  ]
}

Ensure all extracted values and reference ranges are medically accurate. Return strictly valid JSON only.
PROMPT;

        $userPrompt = "Report Document Title: {$reportTitle}\n\nClinical Report Content:\n{$rawText}";
        if (!empty($patientContext)) {
            $userPrompt .= "\n\nPatient Context:\n" . json_encode($patientContext, JSON_PRETTY_PRINT);
        }

        $payload = [
            'model' => self::getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.15,
        ];

        $res = self::makeHttpRequest(self::API_ENDPOINT, $payload);
        if (!$res['success']) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => $res['error']
            ];
        }

        $parsed = json_decode($res['content'], true);
        if (!is_array($parsed) || empty($parsed['biomarkers'])) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => 'Failed to parse structured JSON from OpenAI response.'
            ];
        }

        $parsed['record_id'] = $patientContext['record_id'] ?? rand(100, 999);
        $parsed['ai_engine'] = 'OpenAI ' . self::getModel();
        $parsed['success'] = true;

        return $parsed;
    }

    /**
     * ── LONGITUDINAL HEALTH DATA ANALYSIS VIA OPENAI ───────────
     * Synthesizes historical reports, vital sign metrics, and health timeline
     * to discover long-term trends, risk trajectories, and preventative steps.
     */
    public static function analyzeLongitudinalData(array $historyRecords, array $metrics = []): array {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => 'OpenAI API key is not configured.'
            ];
        }

        $systemPrompt = <<<PROMPT
You are the Lead Clinical Data Scientist and Preventive Health Intelligence Engine for "AayuVault".
Analyze the patient's multi-report longitudinal medical history and vital sign trends.

Return a comprehensive JSON object with this exact structure:
{
  "health_index": 86, // Integer 0-100
  "health_status": "Good Condition" | "Optimal Vitality" | "Attention Required",
  "trajectory_summary": "2-3 sentences summarizing the patient's long-term health trajectory and whether markers are improving, stable, or deteriorating.",
  "improving_parameters": [
    { "name": "e.g. Total Cholesterol", "note": "Decreased from 232 mg/dL to 218 mg/dL over 4 months." }
  ],
  "watchlist_parameters": [
    { "name": "e.g. HbA1c", "current": "5.9%", "target": "< 5.7%", "status": "Pre-diabetic", "action": "Aerobic post-meal walking and carbohydrate management." }
  ],
  "organ_systems": [
    { "name": "Cardiovascular", "score": 76, "status": "Attention Needed", "icon": "fa-heart-pulse", "analysis": "Elevated LDL & triglycerides require ongoing monitoring." },
    { "name": "Metabolic", "score": 79, "status": "Mild Concern", "icon": "fa-fire", "analysis": "Fasting glucose and HbA1c in pre-diabetic interval." },
    { "name": "Liver & Hepatic", "score": 92, "status": "Optimal", "icon": "fa-shield-halved", "analysis": "Enzymes ALT/AST well within baseline." },
    { "name": "Renal & Kidneys", "score": 96, "status": "Optimal", "icon": "fa-droplet", "analysis": "Creatinine and eGFR demonstrate strong filtration." },
    { "name": "Blood & Immune", "score": 94, "status": "Optimal", "icon": "fa-dna", "analysis": "Hemoglobin, platelets, and leukocyte counts normal." }
  ],
  "forecast_risks": [
    { "risk": "Cardiometabolic Syndrome", "probability": "Low-Moderate", "timeline": "6-12 Months", "prevention": "Sustain caloric restriction and 150m weekly cardio." }
  ],
  "action_plan_90_days": [
    "Step 1: Repeat HbA1c and Lipid profile in 90 days to verify downward trend.",
    "Step 2: Maintain daily hydration of 2.5L-3.0L water.",
    "Step 3: Consult nutritionist for low-glycemic dietary planning."
  ],
  "doctor_consultation_brief": "Executive summary paragraph that the patient can print or show directly to their physician during a checkup."
}
Return strictly valid JSON only.
PROMPT;

        $userPrompt = "Patient Medical History Records:\n" . json_encode($historyRecords, JSON_PRETTY_PRINT);
        if (!empty($metrics)) {
            $userPrompt .= "\n\nCurrent Vitals & Metrics:\n" . json_encode($metrics, JSON_PRETTY_PRINT);
        }

        $payload = [
            'model' => self::getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ];

        $res = self::makeHttpRequest(self::API_ENDPOINT, $payload);
        if (!$res['success']) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => $res['error']
            ];
        }

        $parsed = json_decode($res['content'], true);
        if (!is_array($parsed)) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => 'Failed to parse longitudinal JSON response.'
            ];
        }

        $parsed['ai_engine'] = 'OpenAI ' . self::getModel();
        $parsed['success'] = true;
        return $parsed;
    }

    /**
     * ── CONVERSATIONAL AI HEALTH ASSISTANT VIA OPENAI ──────────
     * Provides an empathetic, intelligent medical coach with direct access
     * to the user's records, vitals, appointments, and test trends.
     */
    public static function chatWithHealthData(string $userMessage, array $conversationHistory = [], array $userHealthContext = []): array {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => 'OpenAI API key is not configured.'
            ];
        }

        $systemPrompt = <<<PROMPT
You are "AayuVault AI Health Assistant", an advanced, friendly, and medically grounded personal health intelligence coach.
You have secure access to the user's clinical profile, verified lab records, health vitals, and appointments stored in AayuVault.

Guidelines:
1. Always ground your answers in the user's actual health data provided below (e.g. their specific cholesterol levels, blood glucose, HbA1c, blood pressure, etc.).
2. When the user asks questions about their health, food, diet, workouts, or reports, explain the clinical rationale simply and warmly with bullet points.
3. If they ask about symptoms or medical diagnoses, provide helpful educational context and recommend discussing with their doctor.
4. Format your responses with clean Markdown (bold headings, bullet lists, emojis where appropriate).
5. Always maintain medical safety: you are an AI assistant assisting their wellness journey, not a substitute for in-person physician diagnosis.

Patient Health Context Grounding:
PROMPT;

        if (!empty($userHealthContext)) {
            $systemPrompt .= "\n" . json_encode($userHealthContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $systemPrompt .= "\n(Standard test profile: 35yo adult, Borderline Cholesterol 218 mg/dL, HbA1c 5.9% Pre-diabetic, Normal CBC and Kidney/Liver function).";
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Append recent chat history (up to last 8 messages)
        $recentHistory = array_slice($conversationHistory, -8);
        foreach ($recentHistory as $msg) {
            if (!empty($msg['role']) && !empty($msg['content'])) {
                $messages[] = [
                    'role' => ($msg['role'] === 'user' ? 'user' : 'assistant'),
                    'content' => (string)$msg['content']
                ];
            }
        }

        // Append current message
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model' => self::getModel(),
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 750,
        ];

        $res = self::makeHttpRequest(self::API_ENDPOINT, $payload);
        if (!$res['success']) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => $res['error']
            ];
        }

        return [
            'success' => true,
            'reply' => $res['content'],
            'model' => self::getModel(),
            'ai_engine' => 'OpenAI ' . self::getModel()
        ];
    }

    /**
     * ── REPORT-SPECIFIC INTERACTIVE Q&A VIA OPENAI ─────────────
     */
    public static function askReportQuestion(string $question, array $reportData): array {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => 'OpenAI API key is not configured.'
            ];
        }

        $systemPrompt = <<<PROMPT
You are the AayuVault Clinical Report AI. The user is asking a specific question regarding their active medical report: "{$reportData['report_title']}".
Active Report Data:
PROMPT;
        $systemPrompt .= "\n" . json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $systemPrompt .= "\n\nProvide a concise, direct, helpful, and reassuring answer referencing specific numbers from their report where relevant. Use bullet points for diet/lifestyle suggestions.";

        $payload = [
            'model' => self::getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $question]
            ],
            'temperature' => 0.3,
            'max_tokens' => 500,
        ];

        $res = self::makeHttpRequest(self::API_ENDPOINT, $payload);
        if (!$res['success']) {
            return [
                'success' => false,
                'fallback_needed' => true,
                'error' => $res['error']
            ];
        }

        return [
            'success' => true,
            'question' => $question,
            'answer' => $res['content'],
            'category' => 'OpenAI Clinical Intelligence',
            'ai_engine' => 'OpenAI ' . self::getModel()
        ];
    }
}
