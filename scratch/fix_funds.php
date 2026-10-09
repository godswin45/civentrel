<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

try {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $db->exec('TRUNCATE TABLE `tr_funds`');
    
    $sql = "INSERT INTO `tr_funds` (`id`, `code`, `name`, `balance`, `opening_balance`) VALUES
    ('general', 'GF', 'General Fund', 54300000.00, 50000000.00),
    ('business', 'BSF', 'Business Services Fund', 15000000.00, 15000000.00),
    ('market', 'MSF', 'Market Services Fund', 8300000.00, 8000000.00),
    ('property_tax', 'PTF', 'Property Tax Fund', 22000000.00, 20000000.00),
    ('roads_drainage', 'RDF', 'Roads and Drainage Fund', 17500000.00, 17500000.00),
    ('education', 'EDU', 'Education Fund', 15000000.00, 15000000.00),
    ('health', 'HLTH', 'Health Fund', 24000000.00, 24000000.00),
    ('infrastructure', 'INFRA', 'Infrastructure Fund', 41500000.00, 41500000.00)";
    
    $db->exec($sql);
    
    // Fix the seed data we just created to use the correct original fund IDs and codes
    $tablesToUpdate = ['tr_budget_requests', 'tr_disbursements', 'tr_collections', 'tr_online_payments'];
    
    foreach ($tablesToUpdate as $table) {
        $db->exec("UPDATE `$table` SET fund_id = 'general', fund_code = 'GF' WHERE fund_id = 'GF'");
        $db->exec("UPDATE `$table` SET fund_id = 'education', fund_code = 'EDU' WHERE fund_id = 'SEF'");
    }
    
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "Funds successfully restored to their original 8 categories!";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
