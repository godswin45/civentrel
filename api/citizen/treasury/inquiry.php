<?php
/**
 * POST / GET /api/citizen/treasury/inquiry.php
 *
 * Gemini AI-Powered Taxpayer Inquiry Assistant Endpoint
 * Supports both Citizen Portal / Mobile App requests (with CORS) and Treasury Staff requests.
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

@ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respondInquiry(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0 && ob_get_length() > 0) {
        @ob_clean();
        break;
    }
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $service = $geminiInquiryService ?? new \App\Services\GeminiInquiryService($db ?? null);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = min(max((int) ($_GET['limit'] ?? 20), 1), 100);
        $category = trim((string) ($_GET['category'] ?? ''));
        $inquiries = $service->getRecentInquiries($limit, $category ?: null);

        respondInquiry([
            'status'      => 'success',
            'has_api_key' => $service->hasApiKey(),
            'model'       => $service->getModelName(),
            'count'       => count($inquiries),
            'data'        => $inquiries,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respondInquiry([
            'status'  => 'error',
            'message' => 'Method not allowed. Use POST to submit an inquiry or GET to retrieve history.',
        ], 405);
    }

    $jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
    $input = array_merge($_POST, $jsonInput);

    // Optional admin action: save Gemini API Key
    if (($input['action'] ?? '') === 'save_api_key') {
        if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
            respondInquiry(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        $newKey = trim((string) ($input['api_key'] ?? ''));
        $saved = $service->saveApiKey($newKey);
        respondInquiry([
            'status'      => $saved ? 'success' : 'error',
            'has_api_key' => $service->hasApiKey(),
            'message'     => $saved ? 'Gemini API Key updated successfully.' : 'Could not save API Key.',
        ]);
    }

    // Determine sender role & name from session if not explicitly provided
    if (empty($input['sender_role'])) {
        $input['sender_role'] = (!empty($_SESSION['user_id']) || !empty($_SESSION['employee_id'])) ? 'employee' : 'citizen';
    }
    if (empty($input['sender_name'])) {
        $input['sender_name'] = $headerUser['full_name']
            ?? $_SESSION['citizen_name']
            ?? ($input['sender_role'] === 'employee' ? 'Treasury Staff' : 'Citizen Taxpayer');
    }

    $result = $service->handleInquiry($input);

    if (empty($result['success'])) {
        respondInquiry([
            'status'  => 'error',
            'message' => $result['error'] ?? 'Unable to process inquiry.',
        ], 400);
    }

    respondInquiry([
        'status' => 'success',
        'data'   => $result,
    ]);
} catch (\Throwable $e) {
    respondInquiry([
        'status'  => 'error',
        'message' => 'Internal server error: ' . $e->getMessage(),
    ], 500);
}

