<?php
/**
 * ============================================================
 * AayuVault — OpenAI Settings & Diagnostics API
 * pages/ai_settings.php
 *
 * Checks connection status, updates API key / model preference,
 * and performs live diagnostic pings to OpenAI API.
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

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    switch ($action) {

        // 1. Get status & current configuration
        case 'status':
            $isConfigured = OpenAIService::isConfigured();
            $key = OpenAIService::getApiKey();
            $maskedKey = $key ? (substr($key, 0, 7) . '...' . substr($key, -4)) : 'Not Configured';

            echo json_encode([
                'success' => true,
                'configured' => $isConfigured,
                'model' => OpenAIService::getModel(),
                'masked_key' => $maskedKey,
                'engine' => $isConfigured ? 'OpenAI API (' . OpenAIService::getModel() . ')' : 'Local Clinical Knowledge Engine (Fallback)'
            ]);
            break;

        // 2. Test live connection to OpenAI API
        case 'test_connection':
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?: $_POST;
            $testKey = trim($body['api_key'] ?? '');

            $result = OpenAIService::testConnection($testKey ?: null);
            echo json_encode($result);
            break;

        // 3. Save API Key / Model to config/openai_config.json
        case 'save_settings':
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?: $_POST;

            $apiKey = trim($body['api_key'] ?? '');
            $model = trim($body['model'] ?? 'gpt-4o-mini');

            $cfgDir = __DIR__ . '/../config';
            if (!is_dir($cfgDir)) {
                @mkdir($cfgDir, 0777, true);
            }

            $cfgFile = $cfgDir . '/openai_config.json';
            $configData = [
                'api_key' => $apiKey,
                'model' => $model,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            file_put_contents($cfgFile, json_encode($configData, JSON_PRETTY_PRINT));

            // Also update .env if writable
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $envContent = file_get_contents($envFile);
                $envContent = preg_replace('/^OPENAI_API_KEY=.*$/m', "OPENAI_API_KEY={$apiKey}", $envContent);
                $envContent = preg_replace('/^OPENAI_MODEL=.*$/m', "OPENAI_MODEL={$model}", $envContent);
                @file_put_contents($envFile, $envContent);
            }

            OpenAIService::setApiKey($apiKey);

            echo json_encode([
                'success' => true,
                'message' => 'OpenAI configuration saved successfully!',
                'configured' => !empty($apiKey),
                'model' => $model
            ]);
            break;

        default:
            echo json_encode([
                'success' => true,
                'message' => 'AayuVault OpenAI Settings Engine Active'
            ]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Settings error: ' . $e->getMessage()
    ]);
}
