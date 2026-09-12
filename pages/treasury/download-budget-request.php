<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    exit('Invalid budget request.');
}

$request = $treasuryService->getBudgetRequestById($requestId);
if (!$request) {
    http_response_code(404);
    exit('Budget request not found.');
}

$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$requestNo = $request['request_number'] ?? $request['request_no'] ?? '';
$amount = $treasuryService->formatPeso($request['requested_amount'] ?? 0);
$filename = 'budget-request-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $requestNo) . '.doc';

header('Content-Type: application/msword; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Budget Request <?= $escape($requestNo) ?></title>
  <style>
    body { font-family: Arial, sans-serif; color: #111; margin: 48px; line-height: 1.5; }
    .header { text-align: center; margin-bottom: 28px; }
    .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
    .header p { margin: 4px 0; font-size: 12px; }
    h2 { text-align: center; font-size: 17px; margin: 24px 0; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    th, td { border: 1px solid #222; padding: 9px 10px; vertical-align: top; }
    th { width: 30%; text-align: left; background: #f0f0f0; }
    .justification { min-height: 90px; white-space: pre-wrap; }
    .signature { width: 280px; margin: 70px 0 0 auto; text-align: center; }
    .signature-line { border-bottom: 1px solid #111; height: 28px; }
    .signature-name { font-weight: bold; margin-top: 6px; }
    .signature-title { font-size: 12px; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Civentral Caloocan Portal</h1>
    <p>Budget Management Office</p>
    <p>Official Budget Request Document</p>
  </div>

  <h2>Budget Request</h2>

  <table>
    <tr><th>Request No.</th><td><?= $escape($requestNo) ?></td></tr>
    <tr><th>Department</th><td><?= $escape($request['department_name'] ?? '') ?></td></tr>
    <tr><th>Project</th><td><?= $escape($request['project_title'] ?? $request['description'] ?? '') ?></td></tr>
    <tr><th>Amount</th><td><?= $escape($amount) ?></td></tr>
    <tr><th>Justification</th><td class="justification"><?= $escape($request['justification'] ?? '') ?></td></tr>
  </table>

  <p><strong>Prepared by:</strong> <?= $escape($request['requested_by'] ?? '') ?></p>
  <p><strong>Date submitted:</strong> <?= $escape($request['created_at'] ?? '') ?></p>

  <div class="signature">
    <div class="signature-line"></div>
    <div class="signature-name">HON. MAYOR</div>
    <div class="signature-title">City Mayor</div>
  </div>
</body>
</html>
