<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

$target = $_POST['target'] ?? '';
$redirect = $target === 'budget' ? '../budget/my-requests.php' : 'disbursement.php';
$errors = [];
$imported = 0;

function parseSpreadsheetFile(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('The Excel file is invalid or cannot be opened.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        if ($shared !== false) {
            foreach ($shared->si as $item) {
                $sharedStrings[] = (string) ($item->t ?? implode('', array_map('strval', $item->r->t ?? [])));
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new Exception('The Excel file has no readable first worksheet.');

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) throw new Exception('The Excel worksheet is invalid.');

    $rows = [];
    foreach ($sheet->sheetData->row as $xmlRow) {
        $values = [];
        foreach ($xmlRow->c as $cell) {
            $reference = (string) $cell['r'];
            preg_match('/([A-Z]+)/', $reference, $match);
            $column = 0;
            foreach (str_split($match[1] ?? '') as $letter) $column = ($column * 26) + ord($letter) - 64;
            $value = (string) ($cell->v ?? '');
            if ((string) $cell['t'] === 's') $value = $sharedStrings[(int) $value] ?? '';
            $values[$column - 1] = $value;
        }
        if ($values) {
            ksort($values);
            $rows[] = array_values($values);
        }
    }

    if (count($rows) < 2) throw new Exception('The Excel file must contain a header row and at least one data row.');
    $headers = array_map(static fn($header) => preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $header))), array_shift($rows));
    return array_map(static function ($values) use ($headers) {
        $row = [];
        foreach ($headers as $index => $header) if ($header !== '') $row[$header] = trim((string) ($values[$index] ?? ''));
        return $row;
    }, $rows);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['import_file'])) {
        throw new Exception('Choose a CSV or JSON file to import.');
    }

    $file = $_FILES['import_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('The import file could not be uploaded.');
    }
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new Exception('Import files must be 10 MB or smaller.');
    }

    $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'json', 'xlsx'], true)) {
        throw new Exception('Only CSV, Excel (.xlsx), and JSON files are supported.');
    }

    $rows = [];
    if ($extension === 'xlsx') {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
            throw new Exception('Excel import requires PHP ZIP and SimpleXML extensions.');
        }
        $rows = parseSpreadsheetFile($file['tmp_name']);
    } elseif ($extension === 'json') {
        $decoded = json_decode((string) file_get_contents($file['tmp_name']), true);
        if (!is_array($decoded) || (isset($decoded[0]) && !is_array($decoded[0]))) {
            throw new Exception('JSON must contain an array of objects.');
        }
        $rows = isset($decoded[0]) ? $decoded : [$decoded];
    } else {
        $handle = fopen($file['tmp_name'], 'rb');
        $headers = $handle ? fgetcsv($handle) : false;
        if (!$headers) {
            throw new Exception('The CSV file is empty or invalid.');
        }
        $headers = array_map(static function ($header) {
            $header = strtolower(trim((string) $header));
            return preg_replace('/[^a-z0-9]+/', '_', $header);
        }, $headers);
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, static fn($value) => trim((string) $value) !== '')) === 0) continue;
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') $row[$header] = trim((string) ($values[$index] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);
    }

    if (count($rows) > 1000) {
        throw new Exception('Import is limited to 1,000 rows per upload.');
    }

    if ($target === 'budget') {
        $result = $treasuryService->importBudgetRequests($rows, $headerUser['full_name'] ?? null);
    } elseif ($target === 'disbursement') {
        $result = $treasuryService->importVouchers($rows, $headerUser['full_name'] ?? null);
    } else {
        throw new Exception('Invalid import target.');
    }

    $imported = $result['imported'];
    $errors = $result['errors'];
    if ($auditService) {
        $auditService->logTransaction([
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $headerUser['full_name'] ?? 'System',
            'module' => $target === 'budget' ? 'budget' : 'disbursement',
            'action' => 'import',
            'table_name' => $target === 'budget' ? 'tr_budget_requests' : 'tr_disbursements',
            'new_values' => json_encode(['imported' => $imported, 'errors' => count($errors), 'filename' => $file['name']])
        ]);
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$message = 'Imported ' . $imported . ' row(s).';
if ($errors) $message .= ' ' . count($errors) . ' row(s) were rejected: ' . implode(' | ', array_slice($errors, 0, 5));
header('Location: ' . $redirect . '?imported=' . urlencode($message));
exit;
