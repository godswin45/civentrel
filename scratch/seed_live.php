<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

try {
    // Disable FK checks just in case
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    // 1. Wipe existing transactional data
    $tables = [
        'tr_budget_requests', 'tr_business_apps', 'tr_collections', 
        'tr_disbursements', 'tr_market_stalls', 'tr_online_payments', 
        'tr_tax_assessments', 'tr_funds'
    ];
    foreach($tables as $t) {
        $db->exec("TRUNCATE TABLE `$t`");
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // 2. Seed tr_funds
    $funds = [
        ['id'=>'GF', 'code'=>'GF', 'name'=>'General Fund', 'balance'=>54300000.00, 'opening_balance'=>50000000.00],
        ['id'=>'SEF', 'code'=>'SEF', 'name'=>'Special Education Fund', 'balance'=>12500000.00, 'opening_balance'=>10000000.00],
        ['id'=>'TF', 'code'=>'TF', 'name'=>'Trust Fund', 'balance'=>8200000.00, 'opening_balance'=>5000000.00]
    ];
    foreach($funds as $f) $db->insert('tr_funds', $f);

    // 3. Seed tr_budget_requests
    $budgetReqs = [
        ['request_no'=>'BR-2026-001', 'department_name'=>'Information Technology Department', 'department_code'=>'IT', 'project_title'=>'Server Infrastructure Upgrade', 'description'=>'Procurement of new blade servers for datacenter.', 'budget_type'=>'capital', 'requested_amount'=>1250000.00, 'fund_id'=>'GF', 'fund_code'=>'GF', 'fiscal_year'=>2026, 'quarter'=>'Q4', 'status'=>'approved', 'requested_by'=>'Joshua Suraiz', 'created_at'=>'2026-09-10 10:00:00'],
        ['request_no'=>'BR-2026-002', 'department_name'=>'Social Services Management', 'department_code'=>'SSM', 'project_title'=>'Relief Goods Procurement', 'description'=>'Disaster readiness program for Q4.', 'budget_type'=>'operational', 'requested_amount'=>850000.00, 'fund_id'=>'GF', 'fund_code'=>'GF', 'fiscal_year'=>2026, 'quarter'=>'Q4', 'status'=>'pending', 'requested_by'=>'James Lemi', 'created_at'=>'2026-10-05 09:30:00'],
        ['request_no'=>'BR-2026-003', 'department_name'=>'Education & Scholarship', 'department_code'=>'ESMS', 'project_title'=>'Scholarship Grants Batch 3', 'description'=>'Allowances for city university scholars.', 'budget_type'=>'special_project', 'requested_amount'=>2000000.00, 'fund_id'=>'SEF', 'fund_code'=>'SEF', 'fiscal_year'=>2026, 'quarter'=>'Q4', 'status'=>'released', 'requested_by'=>'John Laurence Gilbuena', 'created_at'=>'2026-09-01 11:20:00']
    ];
    foreach($budgetReqs as $b) $db->insert('tr_budget_requests', $b);

    // 4. Seed tr_business_apps
    $bizApps = [
        ['application_no'=>'BA-2026-901', 'business_name'=>'Caloocan Tech Hub', 'owner_name'=>'Maria Santos', 'business_address'=>'123 Rizal Ave, Caloocan', 'application_type'=>'new', 'transaction_type'=>'renewal', 'gross_essential'=>0, 'gross_non_essential'=>500000, 'assessed_tax'=>15000, 'regulatory_fees'=>2500, 'total_due'=>17500, 'status'=>'paid'],
        ['application_no'=>'BA-2026-902', 'business_name'=>'Aling Nena Sari-Sari', 'owner_name'=>'Nena Dela Cruz', 'business_address'=>'456 Mabini St, Caloocan', 'application_type'=>'renewal', 'transaction_type'=>'renewal', 'gross_essential'=>200000, 'gross_non_essential'=>0, 'assessed_tax'=>3000, 'regulatory_fees'=>1000, 'total_due'=>4000, 'status'=>'assessed']
    ];
    foreach($bizApps as $ba) $db->insert('tr_business_apps', $ba);

    // 5. Seed tr_online_payments
    $onlinePayments = [
        ['payment_reference'=>'PAY-'.uniqid(), 'transaction_id'=>'TXN-2026-001', 'reference_no'=>'REF-001', 'receipt_no'=>'OR-2026-001', 'citizen_id'=>101, 'citizen_name'=>'Maria Santos', 'payment_source'=>'web', 'payment_type'=>'Business Tax & Fees', 'fund_id'=>'GF', 'fund_code'=>'GF', 'amount'=>17500, 'total_amount'=>17500, 'payment_gateway'=>'gcash', 'status'=>'completed', 'created_at'=>'2026-10-01 14:00:00'],
        ['payment_reference'=>'PAY-'.uniqid(), 'transaction_id'=>'TXN-2026-002', 'reference_no'=>'REF-002', 'receipt_no'=>'OR-2026-002', 'citizen_id'=>102, 'citizen_name'=>'Juan Dela Cruz', 'payment_source'=>'mobile', 'payment_type'=>'Community Tax Certificate', 'fund_id'=>'GF', 'fund_code'=>'GF', 'amount'=>500, 'total_amount'=>500, 'payment_gateway'=>'maya', 'status'=>'completed', 'created_at'=>'2026-10-02 09:15:00'],
        ['payment_reference'=>'PAY-'.uniqid(), 'transaction_id'=>'TXN-2026-003', 'reference_no'=>'REF-003', 'receipt_no'=>null, 'citizen_id'=>103, 'citizen_name'=>'Pedro Penduko', 'payment_source'=>'web', 'payment_type'=>'Real Property Tax', 'fund_id'=>'GF', 'fund_code'=>'GF', 'amount'=>1200, 'total_amount'=>1200, 'payment_gateway'=>'card', 'status'=>'pending', 'created_at'=>'2026-10-08 16:30:00']
    ];
    foreach($onlinePayments as $op) $db->insert('tr_online_payments', $op);

    // 6. Seed tr_market_stalls
    $marketStalls = [
        ['stall_number'=>'MS-A01', 'section'=>'Meat Section', 'vendor_name'=>'Jose Rizal Meatshop', 'monthly_fee'=>1500, 'status'=>'occupied', 'last_payment_date'=>'2026-10-01'],
        ['stall_number'=>'MS-B12', 'section'=>'Vegetable Section', 'vendor_name'=>'Aling Nena Veggies', 'monthly_fee'=>1000, 'status'=>'occupied', 'last_payment_date'=>'2026-10-05'],
        ['stall_number'=>'MS-C05', 'section'=>'Dry Goods', 'vendor_name'=>null, 'monthly_fee'=>1200, 'status'=>'vacant', 'last_payment_date'=>null]
    ];
    foreach($marketStalls as $ms) $db->insert('tr_market_stalls', $ms);

    // 7. Seed tr_tax_assessments
    $taxAssessments = [
        ['assessment_no'=>'TA-2026-101', 'taxpayer_name'=>'Caloocan Tech Hub', 'tax_type'=>'Business Tax', 'assessed_value'=>500000, 'tax_due'=>15000, 'due_date'=>'2026-11-30', 'status'=>'paid'],
        ['assessment_no'=>'TA-2026-102', 'taxpayer_name'=>'Aling Nena Sari-Sari', 'tax_type'=>'Business Tax', 'assessed_value'=>200000, 'tax_due'=>3000, 'due_date'=>'2026-11-30', 'status'=>'unpaid']
    ];
    foreach($taxAssessments as $ta) $db->insert('tr_tax_assessments', $ta);

    // 8. Seed tr_disbursements
    $disbursements = [
        ['dv_number'=>'DV-2026-001', 'voucher_no'=>'V-001', 'payee'=>'Tech Supplies Corp', 'purpose'=>'Payment for blade servers (IT Dept)', 'fund_id'=>'GF', 'fund_code'=>'GF', 'amount'=>1250000, 'status'=>'disbursed', 'created_by'=>'Joshua Suraiz', 'disbursement_date'=>'2026-09-15'],
        ['dv_number'=>'DV-2026-002', 'voucher_no'=>'V-002', 'payee'=>'City Scholars Fund', 'purpose'=>'Batch 3 Allowances', 'fund_id'=>'SEF', 'fund_code'=>'SEF', 'amount'=>2000000, 'status'=>'approved', 'created_by'=>'John Laurence Gilbuena', 'disbursement_date'=>null]
    ];
    foreach($disbursements as $d) $db->insert('tr_disbursements', $d);

    echo "Live database successfully wiped and seeded with realistic transactions!";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
