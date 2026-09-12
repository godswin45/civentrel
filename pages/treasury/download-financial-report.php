<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

try {
    $funds = $treasuryService->getFunds();
    $collections = $treasuryService->getAllCollections();
    $vouchers = $treasuryService->getAllVouchers();
    $budgetRequests = $treasuryService->getAllBudgetRequests();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to generate the financial report.');
}

$totalRevenue = array_sum(array_map('floatval', array_column($collections, 'amount')));
$released = array_filter($vouchers, static fn($voucher) => strtolower((string) ($voucher['status'] ?? '')) === 'disbursed');
$totalDisbursed = array_sum(array_map('floatval', array_column($released, 'amount')));
$netPosition = array_sum(array_map('floatval', array_column($funds, 'balance')));
$filename = 'treasury-financial-report-' . date('Y-m-d_His') . '.csv';
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? ''));
$voucherStatusFilter = strtolower(trim((string) ($_GET['voucher_status'] ?? '')));

$collections = array_values(array_filter($collections, static function (array $collection) use ($dateFrom, $dateTo, $sourceFilter): bool {
    $createdAt = substr((string) ($collection['created_at'] ?? ''), 0, 10);
    return ($dateFrom === '' || $createdAt >= $dateFrom)
        && ($dateTo === '' || $createdAt <= $dateTo)
        && ($sourceFilter === '' || ($collection['revenue_source'] ?? '') === $sourceFilter);
}));
$vouchers = array_values(array_filter($vouchers, static function (array $voucher) use ($dateFrom, $dateTo, $voucherStatusFilter): bool {
    $createdAt = substr((string) ($voucher['created_at'] ?? ''), 0, 10);
    return ($dateFrom === '' || $createdAt >= $dateFrom)
        && ($dateTo === '' || $createdAt <= $dateTo)
        && ($voucherStatusFilter === '' || strtolower((string) ($voucher['status'] ?? '')) === $voucherStatusFilter);
}));

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['CIVENTRAL CALOOCAN PORTAL']);
fputcsv($output, ['Treasury Financial Report']);
fputcsv($output, ['Generated At', date('Y-m-d H:i:s')]);
fputcsv($output, []);
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Revenue Collected', number_format($totalRevenue, 2, '.', '')]);
fputcsv($output, ['Total Disbursed', number_format($totalDisbursed, 2, '.', '')]);
fputcsv($output, ['Net Treasury Position', number_format($netPosition, 2, '.', '')]);
fputcsv($output, []);

fputcsv($output, ['REVENUE COLLECTIONS']);
fputcsv($output, ['OR Number', 'Payer', 'Revenue Source', 'Fund', 'Amount', 'Payment Mode', 'Status', 'Collected By', 'Created At']);
foreach ($collections as $collection) {
    fputcsv($output, [
        $collection['or_number'] ?? '',
        $collection['payer_name'] ?? '',
        $collection['revenue_source'] ?? '',
        $collection['fund_code'] ?? $collection['fund_id'] ?? '',
        number_format((float) ($collection['amount'] ?? 0), 2, '.', ''),
        $collection['payment_mode'] ?? '',
        $collection['status'] ?? '',
        $collection['collected_by'] ?? '',
        $collection['created_at'] ?? '',
    ]);
}
fputcsv($output, []);

fputcsv($output, ['DISBURSEMENT VOUCHERS']);
fputcsv($output, ['DV Number', 'Voucher Number', 'Payee', 'Purpose', 'Fund', 'Amount', 'Status', 'Created By', 'Disbursement Date', 'Created At']);
foreach ($vouchers as $voucher) {
    fputcsv($output, [
        $voucher['dv_number'] ?? '',
        $voucher['voucher_no'] ?? '',
        $voucher['payee'] ?? '',
        $voucher['purpose'] ?? '',
        $voucher['fund_code'] ?? $voucher['fund_id'] ?? '',
        number_format((float) ($voucher['amount'] ?? 0), 2, '.', ''),
        $voucher['status'] ?? '',
        $voucher['created_by'] ?? '',
        $voucher['disbursement_date'] ?? '',
        $voucher['created_at'] ?? '',
    ]);
}
fputcsv($output, []);

fputcsv($output, ['BUDGET REQUESTS']);
fputcsv($output, ['Request No.', 'Department', 'Project', 'Requested Amount', 'Fund', 'Fiscal Year', 'Quarter', 'Status', 'Requested By', 'Justification', 'Created At']);
foreach ($budgetRequests as $request) {
    fputcsv($output, [
        $request['request_no'] ?? $request['request_number'] ?? '',
        $request['department_name'] ?? '',
        $request['project_title'] ?? '',
        number_format((float) ($request['requested_amount'] ?? 0), 2, '.', ''),
        $request['fund_code'] ?? $request['fund_id'] ?? '',
        $request['fiscal_year'] ?? '',
        $request['quarter'] ?? '',
        $request['status'] ?? '',
        $request['requested_by'] ?? '',
        $request['justification'] ?? '',
        $request['created_at'] ?? '',
    ]);
}
fputcsv($output, []);

fputcsv($output, ['FUND POSITION']);
fputcsv($output, ['Fund Code', 'Fund Name', 'Opening Balance', 'Current Balance', 'Utilization']);
foreach ($funds as $fund) {
    $opening = (float) ($fund['opening_balance'] ?? 0);
    $balance = (float) ($fund['balance'] ?? 0);
    $utilization = $opening > 0 ? (($opening - $balance) / $opening) * 100 : 0;
    fputcsv($output, [
        $fund['code'] ?? '',
        $fund['name'] ?? '',
        number_format($opening, 2, '.', ''),
        number_format($balance, 2, '.', ''),
        number_format(max(0, $utilization), 2, '.', '') . '%',
    ]);
}

fclose($output);
exit;
