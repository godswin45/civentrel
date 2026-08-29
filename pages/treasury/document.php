<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Auth check
if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../login.php');
    exit;
}

$applicationNo = $_GET['app'] ?? '';
$field = $_GET['field'] ?? '';

if (!$applicationNo || !$field) {
    http_response_code(400);
    exit('Missing parameters');
}

// Get the application
$app = $treasuryService->getBusinessAppByNo($applicationNo);
if (!$app) {
    http_response_code(404);
    exit('Application not found');
}

// Find the document
$document = null;
foreach ($app['documents'] as $doc) {
    if ($doc['field'] === $field) {
        $document = $doc;
        break;
    }
}

if (!$document) {
    http_response_code(404);
    exit('Document not found');
}

// Build file path
$filePath = __DIR__ . '/uploads/' . preg_replace('/[^A-Za-z0-9\-]/', '', $applicationNo) . '/' . $document['filename'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Stream the file
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $document['original'] . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;