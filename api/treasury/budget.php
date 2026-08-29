<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Internal-Service-Key, X-Approved-By');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../src/bootstrap.php';

// Optional API Key check (supports X-API-Key, X-Internal-Service-Key, Authorization)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_INTERNAL_SERVICE_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$method = $_SERVER['REQUEST_METHOD'];

// Flexible path parsing
$rawPath = $_SERVER['PATH_INFO'] ?? '';
if (empty($rawPath)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'budget.php') !== false) {
        $rawPath = substr($uri, strpos($uri, 'budget.php') + strlen('budget.php'));
    }
}
$path = strtok($rawPath, '?') ?: '/budget/requests';
if ($path === '/' || $path === '') {
    $path = '/budget/requests';
}

try {
    switch ($method) {
        case 'GET':
            if ($path === '/budget/requests') {
                // Get all budget requests
                $requests = $treasuryService->getAllBudgetRequests();
                echo json_encode(['status' => 'success', 'success' => true, 'data' => $requests]);
            } elseif (preg_match('#^/budget/requests/(\d+)$#', $path, $matches)) {
                // Get specific budget request
                $request = $treasuryService->getBudgetRequestById((int)$matches[1]);
                if (!$request) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'error' => 'Budget request not found']);
                } else {
                    echo json_encode(['status' => 'success', 'success' => true, 'data' => $request]);
                }
            } elseif (preg_match('#^/budget/department/([^/]+)$#', $path, $matches)) {
                // Get budget requests by department
                $requests = $treasuryService->getBudgetRequestsByDepartment($matches[1]);
                echo json_encode(['status' => 'success', 'success' => true, 'data' => $requests]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'error' => 'Endpoint not found']);
            }
            break;

        case 'POST':
            if ($path === '/budget/requests' || $path === '/' || $path === '') {
                // Create new budget request from another department
                $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                
                if (empty($input['department_name']) || empty($input['project_title']) || empty($input['requested_amount'])) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'error' => 'Missing required fields: department_name, project_title, and requested_amount are required']);
                    exit;
                }

                $request = $treasuryService->createBudgetRequest([
                    'department_name' => $input['department_name'],
                    'department_code' => $input['department_code'] ?? 'GEN',
                    'project_title' => $input['project_title'],
                    'description' => $input['description'] ?? '',
                    'budget_type' => $input['budget_type'] ?? 'operational',
                    'requested_amount' => (float)$input['requested_amount'],
                    'fund_id' => $input['fund_id'] ?? 'GF',
                    'fiscal_year' => $input['fiscal_year'] ?? date('Y'),
                    'quarter' => $input['quarter'] ?? 'Q1',
                    'requested_by' => $input['requested_by'] ?? 'External Department',
                    'justification' => $input['justification'] ?? null,
                ]);

                http_response_code(201);
                echo json_encode(['status' => 'success', 'success' => true, 'message' => 'Budget request submitted successfully', 'data' => $request]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'error' => 'Endpoint not found']);
            }
            break;

        case 'PUT':
            if (preg_match('#^/budget/requests/(\d+)/approve$#', $path, $matches)) {
                // Approve budget request
                $approvedBy = $_SERVER['HTTP_X_APPROVED_BY'] ?? 'API Admin';
                $request = $treasuryService->approveBudgetRequest((int)$matches[1], $approvedBy);
                echo json_encode(['status' => 'success', 'success' => true, 'data' => $request]);
            } elseif (preg_match('#^/budget/requests/(\d+)/reject$#', $path, $matches)) {
                // Reject budget request
                $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                $request = $treasuryService->updateBudgetRequestStatus((int)$matches[1], 'Rejected', [
                    'rejection_reason' => $input['justification'] ?? ($input['rejection_reason'] ?? '')
                ]);
                echo json_encode(['status' => 'success', 'success' => true, 'data' => $request]);
            } elseif (preg_match('#^/budget/requests/(\d+)/release$#', $path, $matches)) {
                // Release budget
                $request = $treasuryService->releaseBudget((int)$matches[1]);
                echo json_encode(['status' => 'success', 'success' => true, 'data' => $request]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'error' => 'Endpoint not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}