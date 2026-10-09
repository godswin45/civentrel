<?php
/**
 * ONE-TIME DEMO DATA SEEDER (Treasury)
 *
 *  - Superadmin / global-access only
 *  - GET  = preview only (nothing is changed)
 *  - POST = (1) copy every transaction table to bak_<timestamp>_<table>
 *           (2) DELETE + INSERT inside ONE DB transaction (rollback on any error)
 *           (3) recompute tr_funds.balance from opening_balance
 *  - Never touches users, roles, permissions, departments, citizens,
 *    tr_funds rows, tr_market_stalls, tr_tax_assessments, or uploaded files.
 *
 * DELETE THIS FILE FROM THE SERVER AFTER USE.
 */
$basePath = '../';
require_once __DIR__ . '/../src/bootstrap.php';

if (empty($headerUser['is_superadmin']) && empty($headerUser['is_global_access'])) {
    http_response_code(403);
    exit('Forbidden: superadmin only. Log in as superadmin first, then open this page again.');
}

date_default_timezone_set('Asia/Manila');
$pdo = $db->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET time_zone = '+08:00'");

// Children first (delete order)
$txTables = ['tr_payment_gateway_log', 'tr_online_payments', 'tr_collections', 'tr_disbursements',
             'tr_budget_requests', 'tr_business_apps', 'tr_audit_log'];

function tableExists(PDO $pdo, string $t): bool {
    return (bool) $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
}
function cols(PDO $pdo, string $t): array {
    static $cache = [];
    if (!isset($cache[$t])) {
        $cache[$t] = array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    }
    return $cache[$t];
}
function insertRow(PDO $pdo, string $t, array $data): int {
    $c = cols($pdo, $t);
    $data = array_filter($data, fn($k) => in_array($k, $c, true), ARRAY_FILTER_USE_KEY);
    $keys = array_keys($data);
    $sql = "INSERT INTO `$t` (`" . implode('`,`', $keys) . "`) VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")";
    $pdo->prepare($sql)->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

$existing = array_values(array_filter($txTables, fn($t) => tableExists($pdo, $t)));
$counts = [];
foreach ($existing as $t) $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
$funds = $pdo->query("SELECT * FROM tr_funds ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['seed_token'])) $_SESSION['seed_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['seed_token'];

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>Demo Seeder</title>
<style>body{font-family:system-ui,Segoe UI,sans-serif;max-width:860px;margin:30px auto;padding:0 16px;color:#1e293b}
table{border-collapse:collapse;width:100%;margin:10px 0}td,th{border:1px solid #cbd5e1;padding:6px 10px;text-align:left}
th{background:#f1f5f9}.ok{color:#047857}.err{color:#b91c1c}.box{background:#fff7ed;border:1px solid #fdba74;padding:12px 16px;border-radius:8px}
button{background:#b91c1c;color:#fff;border:0;padding:12px 22px;border-radius:8px;font-size:15px;cursor:pointer}</style></head><body>';
echo '<h1>Treasury Demo Data Seeder</h1>';

// ============================ PREVIEW ============================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<p>Logged in as <b>' . $h($headerUser['full_name'] ?? 'superadmin') . '</b>. Nothing has been changed yet.</p>';
    echo '<h3>Transaction tables that will be backed up, then cleared</h3><table><tr><th>Table</th><th>Current rows</th></tr>';
    foreach ($counts as $t => $n) echo "<tr><td>{$h($t)}</td><td>{$n}</td></tr>";
    echo '</table>';
    echo '<h3>Funds (rows kept, balance recalculated from opening balance)</h3><table><tr><th>Code</th><th>Name</th><th>Opening</th><th>Current balance</th></tr>';
    foreach ($funds as $f) echo '<tr><td>' . $h($f['code']) . '</td><td>' . $h($f['name']) . '</td><td>' . number_format((float) ($f['opening_balance'] ?? 0), 2) . '</td><td>' . number_format((float) $f['balance'], 2) . '</td></tr>';
    echo '</table>';
    echo '<div class="box"><b>What happens when you click the button:</b><ol>
      <li>Each table above is copied to <code>bak_&lt;timestamp&gt;_&lt;table&gt;</code> (your backup).</li>
      <li>The tables are cleared and about 75 demo transactions dated <b>' . date('M j', strtotime('-2 months')) . ' – ' . date('M j, Y', strtotime('yesterday')) . '</b> are inserted.</li>
      <li>Fund balances are recalculated. If anything fails, everything is rolled back.</li></ol>
      <b>Not touched:</b> users, roles, departments, citizens, fund rows, market stall list, tax assessments, uploaded files.</div><br>';
    echo '<form method="post" onsubmit="return confirm(\'Backup, clear and seed demo data now?\')">
      <input type="hidden" name="token" value="' . $h($token) . '">
      <button type="submit">Backup, clear, and seed demo data</button></form></body></html>';
    exit;
}

if (!hash_equals($token, $_POST['token'] ?? '')) exit('<p class="err">Invalid token. Reload the page.</p>');
unset($_SESSION['seed_token']);
if (empty($funds)) exit('<p class="err">tr_funds is empty. Aborted, nothing changed.</p>');

// ============================ 1. BACKUP ============================
$stamp = date('ymd_Hi');
foreach ($existing as $t) {
    $bak = "bak_{$stamp}_{$t}";
    $pdo->exec("CREATE TABLE `$bak` LIKE `$t`");
    $pdo->exec("INSERT INTO `$bak` SELECT * FROM `$t`");
    echo "<div class='ok'>Backed up {$h($t)} → {$h($bak)} ({$counts[$t]} rows)</div>";
}

// Schema prerequisites the app also self-heals (DDL must run outside the transaction)
try { $pdo->exec("ALTER TABLE tr_disbursements MODIFY COLUMN status ENUM('pending','approved','rejected','disbursed','cancelled') NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}
try { if (!in_array('rejection_reason', cols($pdo, 'tr_disbursements'), true)) $pdo->exec("ALTER TABLE tr_disbursements ADD COLUMN rejection_reason TEXT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE tr_audit_log MODIFY COLUMN user_id VARCHAR(255) NULL"); } catch (Throwable $e) {}

// ============================ helpers ============================
mt_srand(20261010);
$fundBy = [];
foreach ($funds as $f) { $fundBy[strtoupper($f['code'])] = $f; $fundBy[strtoupper($f['id'])] = $f; }
$fund = function (string $code) use ($fundBy, $funds) {
    return $fundBy[strtoupper($code)] ?? $fundBy['GF'] ?? $fundBy['GENERAL'] ?? $funds[0];
};
$winStart = strtotime(date('Y-m-d', strtotime('-2 months')) . ' 08:00:00');
$winEnd   = strtotime(date('Y-m-d', strtotime('yesterday')) . ' 16:30:00');
// n business-hour datetimes spread evenly across the window
$spread = function (int $n) use ($winStart, $winEnd): array {
    $out = []; $slot = ($winEnd - $winStart) / max($n, 1);
    for ($i = 0; $i < $n; $i++) {
        $ts = (int) ($winStart + $slot * $i + mt_rand(0, (int) max($slot - 1, 0)));
        while ((int) date('N', $ts) >= 6) $ts -= 86400;               // no weekends
        if ($ts < $winStart) $ts += 86400 * 2;
        $ts = strtotime(date('Y-m-d', $ts)) + mt_rand(8 * 3600, 16 * 3600 + 1800); // 8:00–16:30
        $out[] = $ts;
    }
    sort($out);
    return $out;
};
$later = function (int $ts, int $minDays, int $maxDays) use ($winEnd): int {
    $t = strtotime(date('Y-m-d', $ts) . ' +' . mt_rand($minDays, $maxDays) . ' days') + mt_rand(9 * 3600, 16 * 3600);
    while ((int) date('N', $t) >= 6) $t += 86400;
    return min($t, $winEnd);
};
$usedNo = [];
$uid = function (string $prefix, int $ts) use (&$usedNo): string {
    do { $no = $prefix . '-' . date('Y', $ts) . '-' . strtoupper(bin2hex(random_bytes(3))); } while (isset($usedNo[$no]));
    $usedNo[$no] = true; return $no;
};
$dt = fn(int $ts) => date('Y-m-d H:i:s', $ts);
$staff    = $headerUser['full_name'] ?? 'Treasury Officer';
$staffId  = $_SESSION['user_id'] ?? ($_SESSION['employee_id'] ?? null);
$ips      = ['192.168.10.21', '192.168.10.34', '192.168.10.17'];

$audit = function (string $module, string $action, string $table, int $id, array $vals, int $ts, ?string $user = null) use ($pdo, $staff, $staffId, $ips, $dt) {
    insertRow($pdo, 'tr_audit_log', [
        'user_id' => $staffId, 'username' => $user ?? $staff, 'module' => $module, 'action' => $action,
        'table_name' => $table, 'record_id' => $id, 'new_values' => json_encode($vals),
        'ip_address' => $ips[array_rand($ips)], 'created_at' => $dt($ts),
    ]);
};
$collect = function (array $c, int $ts, string $module, ?string $collector = null) use ($pdo, $uid, $dt, $audit, $staff) {
    $row = [
        'or_number' => $c['or'] ?? $uid('OR', $ts), 'payer_name' => $c['payer'], 'revenue_source' => $c['source'],
        'fund_id' => $c['fund']['id'], 'fund_code' => $c['fund']['code'], 'amount' => $c['amount'],
        'payment_mode' => $c['mode'], 'collected_by' => $collector ?? $staff, 'status' => 'valid',
        'created_at' => $dt($ts), 'updated_at' => $dt($ts),
    ];
    $row['id'] = insertRow($pdo, 'tr_collections', $row);
    if ($module !== '') $audit($module, 'collect', 'tr_collections', $row['id'], $row, $ts);
    return $row;
};

// ============================ 2. CLEAR + SEED ============================
$summary = [];
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->beginTransaction();
    foreach ($existing as $t) $pdo->exec("DELETE FROM `$t`");

    // ---- Business Tax (module business_tax) ----
    $biz = [
        ["Aling Nena's Sari-Sari Store", "Mayor's Permit Fee", 1500, 'cash'],
        ['JM Hardware & Construction Supply', 'Business Tax', 8750, 'cash'],
        ['Kusina ni Lola Carinderia', 'Sanitary Inspection Fee', 500, 'cash'],
        ['RBG Water Refilling Station', 'Sanitary Inspection Fee', 650, 'online'],
        ['Santos Rice Trading', 'Business Tax', 6200, 'cash'],
        ['Bayanihan Pharmacy', 'Fire Safety Fee', 1200, 'cash'],
        ['Delos Reyes Auto Repair', 'Zoning Fee', 800, 'cash'],
        ["Mang Tonyo's Bakery", "Mayor's Permit Fee", 1500, 'online'],
        ['GreenLeaf Internet Cafe', 'Electrical Inspection Fee', 950, 'cash'],
        ['Villanueva Building Supply', 'Building Permit Fee', 4500, 'cash'],
        ['Tindahan ni Aling Rosa', 'Business Tax', 2300, 'cash'],
        ['Cruz Lechon House', 'Plumbing Fee', 700, 'cash'],
        ['StarBright Laundry Shop', 'Fire Safety Fee', 1200, 'gcash'],
        ['Garcia Motorparts', 'Business Tax', 5400, 'cash'],
        ['Perez Beauty Salon', "Mayor's Permit Fee", 1500, 'cash'],
    ];
    foreach ($spread(count($biz)) as $i => $ts) {
        [$n, $type, $amt, $mode] = $biz[$i];
        $collect(['payer' => $n, 'source' => $type, 'fund' => $fund('BSF'), 'amount' => $amt, 'mode' => $mode], $ts, 'business_tax');
    }
    $summary['Business Tax collections'] = count($biz);

    // ---- Market Stall (module market_stall) ----
    $stalls = [
        ['Maria Dela Cruz', 1200, 'cash'], ['Roberto Aquino', 1200, 'cash'], ['Lorna Bautista', 3400, 'cash'],
        ['Jun Mendoza', 800, 'gcash'], ['Maria Dela Cruz', 1200, 'cash'], ['Roberto Aquino', 1200, 'cash'],
        ['Elena Ramos', 1500, 'cash'], ['Jun Mendoza', 800, 'gcash'], ['Carlos Navarro', 12000, 'cash'],
        ['Maria Dela Cruz', 1200, 'cash'], ['Roberto Aquino', 1200, 'online'], ['Elena Ramos', 1500, 'cash'],
    ];
    foreach ($spread(count($stalls)) as $i => $ts) {
        [$n, $amt, $mode] = $stalls[$i];
        $collect(['payer' => $n, 'source' => 'Market Stall Rental', 'fund' => $fund('MSF'), 'amount' => $amt, 'mode' => $mode], $ts, 'market_stall');
    }
    $summary['Market Stall collections'] = count($stalls);

    // ---- Revenue Collection (module collection) ----
    $rev = [
        ['Ricardo Santos', 'Real Property Tax', 4850, 'cash'], ['Josefina Reyes', 'Community Tax Certificate', 125, 'cash'],
        ['Antonio Lim', 'Real Property Tax', 7320, 'cash'], ['Grace Fernandez', 'Miscellaneous Fees', 350, 'online'],
        ['Pedro Villanueva', 'Market & Slaughterhouse Fees', 1100, 'cash'], ['Ana Castillo', 'Community Tax Certificate', 98, 'cash'],
        ['Manuel Torres', 'Real Property Tax', 12640, 'cash'], ['JM Hardware & Construction Supply', 'Business Permit & License', 3000, 'cash'],
        ['Liza Gonzales', 'Miscellaneous Fees', 200, 'gcash'], ['Ernesto Pascual', 'Market & Slaughterhouse Fees', 950, 'cash'],
        ['Teresa Morales', 'Real Property Tax', 5975, 'cash'], ['Danilo Rivera', 'Community Tax Certificate', 145, 'cash'],
        ['Bayanihan Pharmacy', 'Business Permit & License', 2500, 'bank_transfer'], ['Corazon Flores', 'Real Property Tax', 3280, 'cash'],
        ['Ramon Herrera', 'Miscellaneous Fees', 400, 'cash'],
    ];
    $revFund = ['Real Property Tax' => 'PTF', 'Market & Slaughterhouse Fees' => 'MSF', 'Business Permit & License' => 'BSF'];
    foreach ($spread(count($rev)) as $i => $ts) {
        [$n, $src, $amt, $mode] = $rev[$i];
        $collect(['payer' => $n, 'source' => $src, 'fund' => $fund($revFund[$src] ?? 'GF'), 'amount' => $amt, 'mode' => $mode], $ts, 'collection');
    }
    $summary['Revenue collections'] = count($rev);

    // ---- Online Payments (module online_payment) — completed ones also create a collection, like the real gateway flow ----
    $onl = [
        ['Juan Dela Cruz', 'Community Tax Certificate', 130, 'gcash', 'completed'],
        ['Maria Santos', 'Real Property Tax', 3450, 'maya', 'completed'],
        ['Pedro Reyes', 'Business Permit Renewal', 1500, 'gcash', 'completed'],
        ['Ana Lopez', 'Barangay Clearance Fee', 150, 'gcash', 'completed'],
        ['Carmela Uy', 'Real Property Tax', 2780, 'maya', 'completed'],
        ['Mark Anthony Tan', 'Community Tax Certificate', 110, 'gcash', 'failed'],
        ['Jose Garcia', 'Market Stall Rental', 1200, 'gcash', 'pending'],
    ];
    $onlFund = ['Real Property Tax' => 'PTF', 'Business Permit Renewal' => 'BSF', 'Market Stall Rental' => 'MSF'];
    foreach ($spread(count($onl)) as $i => $ts) {
        [$n, $src, $amt, $gw, $st] = $onl[$i];
        $f = $fund($onlFund[$src] ?? 'GF');
        $ref = $uid('PAY', $ts); $or = $st === 'completed' ? $uid('OR', $ts) : null;
        $settle = $st === 'completed' ? $ts + mt_rand(60, 600) : null;
        $row = [
            'payment_reference' => $ref, 'transaction_id' => 'TXN-' . date('Ymd', $ts) . '-' . strtoupper(bin2hex(random_bytes(3))),
            'reference_no' => 'REF-' . date('Ymd', $ts) . '-' . strtoupper(bin2hex(random_bytes(3))),
            'receipt_no' => $or, 'citizen_name' => $n, 'taxpayer_name' => $n, 'payment_source' => $src, 'payment_type' => $src,
            'fund_id' => $f['id'], 'fund_code' => $f['code'], 'amount' => $amt, 'service_fee' => 0, 'total_amount' => $amt,
            'payment_gateway' => $gw, 'payment_method' => $gw, 'gateway_reference' => strtoupper($gw) . mt_rand(10000000, 99999999),
            'or_number' => $or, 'status' => $st, 'source' => 'web',
            'created_at' => $dt($ts), 'updated_at' => $dt($settle ?? $ts), 'settled_at' => $settle ? $dt($settle) : null,
        ];
        $row['id'] = insertRow($pdo, 'tr_online_payments', $row);
        $audit('online_payment', 'create', 'tr_online_payments', $row['id'], $row, $ts, $n);
        if ($st === 'completed') {
            $collect(['or' => $or, 'payer' => $n, 'source' => $src, 'fund' => $f, 'amount' => $amt, 'mode' => $gw === 'maya' ? 'maya' : 'gcash'],
                     $settle, '', 'Online Payment System');
            $audit('online_payment', 'verify', 'tr_online_payments', $row['id'], ['payment_reference' => $ref, 'status' => 'completed', 'or_number' => $or], $settle);
        }
    }
    $summary['Online payments'] = count($onl);

    // ---- Disbursement Vouchers (module disbursement) ----
    $dv = [
        ['Meralco', 'Electricity bill - Municipal Hall (July)', 48500, 'GF', 'disbursed'],
        ['ABC Office Supplies', 'Bond paper, ink and office supplies', 15750, 'GF', 'disbursed'],
        ['Juan Construction Services', 'Repair of Barangay Hall roof', 85000, 'INFRA', 'disbursed'],
        ['Prime Water District', 'Water bill - Municipal Hall (August)', 6200, 'GF', 'disbursed'],
        ['XYZ Catering Services', 'Snacks for Barangay Assembly', 22000, 'GF', 'rejected', 'No approved activity request attached'],
        ['Petron Gas Station', 'Fuel for ambulance and service vehicles', 18400, 'HLTH', 'disbursed'],
        ['MedSupply PH', 'Medicines for Rural Health Unit', 64300, 'HLTH', 'disbursed'],
        ['Meralco', 'Electricity bill - Municipal Hall (September)', 51200, 'GF', 'approved'],
        ['TechZone Computers', '2 laptops for Treasury Office', 78000, 'GF', 'rejected', 'Exceeds allotted budget for the quarter'],
        ['DPWH Accredited Supplier', 'Drainage materials for Purok 3', 42000, 'RDF', 'pending'],
    ];
    foreach ($spread(count($dv)) as $i => $ts) {
        [$payee, $purpose, $amt, $fc, $st] = $dv[$i];
        $f = $fund($fc); $done = $st === 'pending' ? $ts : $later($ts, 1, 3);
        $row = [
            'dv_number' => $uid('DV', $ts), 'voucher_no' => $uid('VCH', $ts), 'payee' => $payee, 'purpose' => $purpose,
            'fund_id' => $f['id'], 'fund_code' => $f['code'], 'amount' => $amt, 'status' => $st, 'created_by' => $staff,
            'disbursement_date' => $st === 'disbursed' ? date('Y-m-d', $done) : null,
            'rejection_reason' => $dv[$i][5] ?? null, 'created_at' => $dt($ts), 'updated_at' => $dt($done),
        ];
        $row['id'] = insertRow($pdo, 'tr_disbursements', $row);
        $audit('disbursement', 'create', 'tr_disbursements', $row['id'], array_merge($row, ['status' => 'pending']), $ts);
        if ($st === 'rejected') $audit('disbursement', 'reject', 'tr_disbursements', $row['id'], $row, $done);
        if (in_array($st, ['approved', 'disbursed'], true)) $audit('disbursement', 'approve', 'tr_disbursements', $row['id'], array_merge($row, ['status' => 'approved']), $done);
        if ($st === 'disbursed') $audit('disbursement', 'disburse', 'tr_disbursements', $row['id'], $row, min($done + 3600, $winEnd));
    }
    $summary['Disbursement vouchers'] = count($dv);

    // ---- Budget Requests (module budget) ----
    $br = [
        ['Municipal Health Office', 'MHO', 'Dengue Prevention Drive', 'Larvicides, fogging chemicals and IEC materials', 120000, 'HLTH', 'operational', 'released', 'Dr. Liza Manalo'],
        ['MSWDO', 'MSWDO', 'Senior Citizen Financial Assistance', 'Quarterly assistance for indigent senior citizens', 250000, 'GF', 'special_project', 'approved', 'Rosario Dizon'],
        ['MDRRMO', 'MDRRMO', 'Typhoon Season Rescue Equipment', 'Life vests, rubber boat and emergency lights', 180000, 'GF', 'capital', 'released', 'Engr. Paolo Cruz'],
        ['Municipal Engineering Office', 'MEO', 'Road Repair - Brgy. San Isidro', 'Concrete patching of 1.2 km barangay road', 500000, 'RDF', 'capital', 'rejected', 'Engr. Victor Ramos', 'Deferred to next quarter due to fund availability'],
        ['Municipal Agriculture Office', 'MAO', 'Seed Distribution Program', 'Certified rice and vegetable seeds for farmers', 95000, 'GF', 'operational', 'pending', 'Arnel Santiago'],
        ['Municipal Education Office', 'MEDO', 'School Supplies for Public Schools', 'Notebooks and kits for Grade 1-6 learners', 150000, 'EDU', 'special_project', 'pending', 'Gemma Ocampo'],
    ];
    foreach ($spread(count($br)) as $i => $ts) {
        [$dn, $dc, $title, $desc, $amt, $fc, $type, $st, $by] = $br[$i];
        $f = $fund($fc); $rev = $later($ts, 1, 4); $rel = $later($rev, 1, 3);
        $row = [
            'request_no' => $uid('BR', $ts), 'department_name' => $dn, 'department_code' => $dc, 'project_title' => $title,
            'description' => $desc, 'budget_type' => $type, 'requested_amount' => $amt, 'fund_id' => $f['id'], 'fund_code' => $f['code'],
            'fiscal_year' => (int) date('Y', $ts), 'quarter' => 'Q' . (int) ceil(date('n', $ts) / 3), 'status' => $st,
            'requested_by' => $by, 'justification' => $desc,
            'reviewed_by' => $st !== 'pending' ? $staff : null,
            'approved_by' => in_array($st, ['approved', 'released'], true) ? $staff : null,
            'approved_at' => in_array($st, ['approved', 'released'], true) ? $dt($rev) : null,
            'rejection_reason' => $br[$i][9] ?? null,
            'created_at' => $dt($ts), 'updated_at' => $dt($st === 'released' ? $rel : ($st === 'pending' ? $ts : $rev)),
        ];
        $row['request_number'] = $row['request_no'];
        $row['id'] = insertRow($pdo, 'tr_budget_requests', $row);
        $audit('budget', 'create', 'tr_budget_requests', $row['id'], array_merge($row, ['status' => 'pending']), $ts, $by);
        if ($st === 'rejected') $audit('budget', 'reject', 'tr_budget_requests', $row['id'], $row, $rev);
        if (in_array($st, ['approved', 'released'], true)) $audit('budget', 'approve', 'tr_budget_requests', $row['id'], array_merge($row, ['status' => 'approved']), $rev);
        if ($st === 'released') $audit('budget', 'disburse', 'tr_budget_requests', $row['id'], $row, $rel);
    }
    $summary['Budget requests'] = count($br);

    // ---- Business Permit Applications (module business) ----
    $apps = [
        ['Santos Rice Trading', 'Ricardo Santos', 'Purok 2, Brgy. Poblacion', 'renewal', 'renewal', 850000, 0, 'issued'],
        ['Bayanihan Pharmacy', 'Liza Bayani', 'Rizal St., Brgy. San Jose', 'renewal', 'renewal', 1200000, 0, 'paid'],
        ['StarBright Laundry Shop', 'Mark Estrada', 'Mabini St., Brgy. Sto. Nino', 'new', 'renewal', 0, 320000, 'assessed'],
        ['Cruz Lechon House', 'Benjie Cruz', 'National Hwy, Brgy. Bagong Silang', 'renewal', 'renewal', 0, 640000, 'submitted'],
        ['Old Town Videoke Bar', 'Ramil Soriano', 'Purok 5, Brgy. Poblacion', 'renewal', 'retirement', 0, 180000, 'issued'],
        ['Quick Print Copy Center', 'Jenny Lim', 'Luna St., Brgy. San Roque', 'new', 'renewal', 0, 150000, 'rejected'],
    ];
    foreach ($spread(count($apps)) as $i => $ts) {
        [$bn, $own, $addr, $atype, $ttype, $ge, $gne, $st] = $apps[$i];
        $tax = round($ge * 0.005 + $gne * 0.01, 2); $fees = 1500 + 500 + 1200;
        $row = [
            'application_no' => $uid('BPA', $ts), 'business_name' => $bn, 'owner_name' => $own, 'business_address' => $addr,
            'application_type' => $atype, 'transaction_type' => $ttype, 'gross_essential' => $ge, 'gross_non_essential' => $gne,
            'assessed_tax' => $st === 'submitted' ? 0 : $tax, 'regulatory_fees' => $st === 'submitted' ? 0 : $fees,
            'total_due' => $st === 'submitted' ? 0 : $tax + $fees, 'status' => $st, 'documents' => json_encode([]),
            'created_at' => $dt($ts), 'updated_at' => $dt($st === 'submitted' ? $ts : $later($ts, 1, 5)),
        ];
        $row['id'] = insertRow($pdo, 'tr_business_apps', $row);
        $audit('business', 'create', 'tr_business_apps', $row['id'], array_merge($row, ['status' => 'submitted']), $ts);
        if ($st !== 'submitted') $audit('business', 'update', 'tr_business_apps', $row['id'], $row, strtotime($row['updated_at']));
    }
    $summary['Business permit applications'] = count($apps);

    // ---- 3. Recompute fund balances ----
    $fc = cols($pdo, 'tr_funds');
    if (in_array('opening_balance', $fc, true)) {
        $collStatus = in_array('status', cols($pdo, 'tr_collections'), true) ? "AND c.status = 'valid'" : '';
        $pdo->exec("UPDATE tr_funds f SET balance = f.opening_balance
            + COALESCE((SELECT SUM(c.amount) FROM tr_collections c WHERE (c.fund_code = f.code OR c.fund_code = f.id) $collStatus), 0)
            - COALESCE((SELECT SUM(d.amount) FROM tr_disbursements d WHERE d.status = 'disbursed' AND (d.fund_code = f.code OR d.fund_code = f.id)), 0)
            - COALESCE((SELECT SUM(b.requested_amount) FROM tr_budget_requests b WHERE b.status = 'released' AND (b.fund_code = f.code OR b.fund_code = f.id)), 0)");
    } else {
        echo "<div class='err'>tr_funds has no opening_balance column; balances were left unchanged.</div>";
    }

    $pdo->commit();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "<h2 class='err'>FAILED — rolled back, your data is unchanged.</h2><pre>" . $h($e->getMessage()) . "</pre>";
    echo "<p>Backup tables <code>bak_{$h($stamp)}_*</code> were still created; they are safe to keep or drop.</p></body></html>";
    exit;
}

echo "<h2 class='ok'>Done! Demo data seeded.</h2><table><tr><th>Module</th><th>Records</th></tr>";
foreach ($summary as $k => $v) echo "<tr><td>{$h($k)}</td><td>{$v}</td></tr>";
echo '</table><h3>New fund balances</h3><table><tr><th>Code</th><th>Name</th><th>Balance</th></tr>';
foreach ($pdo->query("SELECT code, name, balance FROM tr_funds ORDER BY id") as $f)
    echo '<tr><td>' . $h($f['code']) . '</td><td>' . $h($f['name']) . '</td><td>' . number_format((float) $f['balance'], 2) . '</td></tr>';
echo "</table><p>Backups: <code>bak_{$h($stamp)}_*</code>. <a href='../pages/treasury/index.php'>Open the Treasury dashboard →</a></p>";
echo '<p class="err"><b>Tell your assistant it worked so this file can be deleted from the server.</b></p></body></html>';
