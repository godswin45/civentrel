<?php
/**
 * ============================================================
 * CIVENTRAL Treasury Budget Request API
 * ------------------------------------------------------------
 * Base URL (Local):       http://localhost/civentrel/api/treasury/budget.php
 * Base URL (Production):  https://revenue.civentral.tech/api/treasury/budget.php
 *
 * ENDPOINTS:
 *  POST   /budget/requests              — Submit a budget request
 *  GET    /budget/requests              — List all budget requests
 *  GET    /budget/requests/{id}         — Get a specific request by ID
 *  GET    /budget/department/{code}     — Get requests by department code
 *  PUT    /budget/requests/{id}/approve — Approve a budget request
 *  PUT    /budget/requests/{id}/reject  — Reject a budget request
 *  PUT    /budget/requests/{id}/release — Release approved budget
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Internal-Service-Key, X-Approved-By');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../src/bootstrap.php';
date_default_timezone_set('Asia/Manila');

// ── Helper ──────────────────────────────────────────────────────────────────
function apiRespond(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ── Optional API Key Authentication ─────────────────────────────────────────
// To require API key authentication, set BUDGET_API_KEY in your .env file.
// Requests must then include the header:  X-API-Key: <your-key>
$requiredKey = getenv('BUDGET_API_KEY') ?: null;
if ($requiredKey) {
    $providedKey = $_SERVER['HTTP_X_API_KEY']
        ?? $_SERVER['HTTP_X_INTERNAL_SERVICE_KEY']
        ?? (str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? ''))
        ?: null;
    if (!$providedKey || !hash_equals($requiredKey, $providedKey)) {
        apiRespond([
            'status'  => 'error',
            'message' => 'Unauthorized. Provide a valid X-API-Key header.',
        ], 401);
    }
}

// ── Path Parsing ─────────────────────────────────────────────────────────────
$method  = $_SERVER['REQUEST_METHOD'];
$rawPath = $_SERVER['PATH_INFO'] ?? '';
if (empty($rawPath)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $pos = strpos($uri, 'budget.php');
    if ($pos !== false) {
        $rawPath = substr($uri, $pos + strlen('budget.php'));
    }
}
$path = strtok($rawPath, '?') ?: '/budget/requests';
if ($path === '/' || $path === '') {
    $path = '/budget/requests';
}

// ── Route Handling ────────────────────────────────────────────────────────────
try {
    switch ($method) {

        // ── GET ──────────────────────────────────────────────────────────────
        case 'GET':
            // GET /budget/requests — List all
            if ($path === '/budget/requests') {
                $requests = $treasuryService->getAllBudgetRequests();
                apiRespond([
                    'status' => 'success',
                    'count'  => count($requests),
                    'data'   => $requests,
                ]);
            }

            // GET /budget/requests/{id} — Single request
            if (preg_match('#^/budget/requests/(\d+)$#', $path, $m)) {
                $request = $treasuryService->getBudgetRequestById((int)$m[1]);
                if (!$request) {
                    apiRespond(['status' => 'error', 'message' => 'Budget request not found.'], 404);
                }
                apiRespond(['status' => 'success', 'data' => $request]);
            }

            // GET /budget/department/{code} — By department
            if (preg_match('#^/budget/department/([^/]+)$#', $path, $m)) {
                $requests = $treasuryService->getBudgetRequestsByDepartment(urldecode($m[1]));
                apiRespond([
                    'status' => 'success',
                    'count'  => count($requests),
                    'data'   => $requests,
                ]);
            }

            apiRespond(['status' => 'error', 'message' => 'GET endpoint not found.'], 404);

        // ── POST ─────────────────────────────────────────────────────────────
        case 'POST':
            if ($path === '/budget/requests' || $path === '/' || $path === '') {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];

                // Validation
                $required = ['department_name', 'project_title', 'requested_amount'];
                $missing  = array_filter($required, fn($f) => empty($input[$f]));
                if ($missing) {
                    apiRespond([
                        'status'  => 'error',
                        'message' => 'Missing required fields: ' . implode(', ', $missing),
                        'required_fields' => [
                            'department_name'  => 'string — Name of the requesting department',
                            'department_code'  => 'string — Short department code (e.g. HEALTH, ENGG)',
                            'project_title'    => 'string — Title of the project or budget item',
                            'requested_amount' => 'number — Amount requested in PHP (e.g. 50000)',
                            'budget_type'      => 'string — operational | capital | supplemental | emergency',
                            'description'      => 'string — Detailed description of the request',
                            'justification'    => 'string — Why this budget is needed',
                            'requested_by'     => 'string — Name of the person or system submitting',
                            'fiscal_year'      => 'integer — e.g. 2026',
                            'quarter'          => 'string — Q1 | Q2 | Q3 | Q4',
                            'fund_id'          => 'string — GF (General Fund) | SEF | TF | etc.',
                        ],
                    ], 422);
                }

                if (!is_numeric($input['requested_amount']) || (float)$input['requested_amount'] <= 0) {
                    apiRespond(['status' => 'error', 'message' => '`requested_amount` must be a positive number.'], 422);
                }

                $validBudgetTypes = ['operational', 'capital', 'supplemental', 'emergency'];
                $budgetType = strtolower($input['budget_type'] ?? 'operational');
                if (!in_array($budgetType, $validBudgetTypes)) {
                    apiRespond([
                        'status'  => 'error',
                        'message' => 'Invalid `budget_type`. Must be one of: ' . implode(', ', $validBudgetTypes),
                    ], 422);
                }

                $result = $treasuryService->createBudgetRequest([
                    'department_name'  => trim($input['department_name']),
                    'department_code'  => strtoupper(trim($input['department_code'] ?? 'GEN')),
                    'project_title'    => trim($input['project_title']),
                    'description'      => trim($input['description'] ?? ''),
                    'budget_type'      => $budgetType,
                    'requested_amount' => (float)$input['requested_amount'],
                    'fund_id'          => strtoupper(trim($input['fund_id'] ?? 'GF')),
                    'fiscal_year'      => (int)($input['fiscal_year'] ?? date('Y')),
                    'quarter'          => $input['quarter'] ?? 'Q' . ceil(date('n') / 3),
                    'requested_by'     => trim($input['requested_by'] ?? 'External API'),
                    'justification'    => trim($input['justification'] ?? ''),
                ]);

                apiRespond([
                    'status'  => 'success',
                    'message' => 'Budget request submitted successfully and is now pending review.',
                    'data'    => $result,
                ], 201);
            }

            apiRespond(['status' => 'error', 'message' => 'POST endpoint not found.'], 404);

        // ── PUT ──────────────────────────────────────────────────────────────
        case 'PUT':
            // PUT /budget/requests/{id}/approve
            if (preg_match('#^/budget/requests/(\d+)/approve$#', $path, $m)) {
                $approvedBy = $_SERVER['HTTP_X_APPROVED_BY'] ?? 'API Admin';
                $result = $treasuryService->approveBudgetRequest((int)$m[1], $approvedBy);
                apiRespond([
                    'status'  => 'success',
                    'message' => 'Budget request approved.',
                    'data'    => $result,
                ]);
            }

            // PUT /budget/requests/{id}/reject
            if (preg_match('#^/budget/requests/(\d+)/reject$#', $path, $m)) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $result = $treasuryService->updateBudgetRequestStatus((int)$m[1], 'Rejected', [
                    'rejection_reason' => trim($input['rejection_reason'] ?? $input['justification'] ?? 'No reason provided.'),
                ]);
                apiRespond([
                    'status'  => 'success',
                    'message' => 'Budget request rejected.',
                    'data'    => $result,
                ]);
            }

            // PUT /budget/requests/{id}/release
            if (preg_match('#^/budget/requests/(\d+)/release$#', $path, $m)) {
                $result = $treasuryService->releaseBudget((int)$m[1]);
                apiRespond([
                    'status'  => 'success',
                    'message' => 'Budget released successfully.',
                    'data'    => $result,
                ]);
            }

            apiRespond(['status' => 'error', 'message' => 'PUT endpoint not found.'], 404);

        default:
            apiRespond(['status' => 'error', 'message' => 'Method not allowed.'], 405);
    }

} catch (Throwable $e) {
    error_log('[Budget API Error] ' . $e->getMessage());
    apiRespond([
        'status'  => 'error',
        'message' => 'Internal server error. Please contact system administrator.',
    ], 500);
}