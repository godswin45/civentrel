<?php

namespace App\Services;

use PDO;

/**
 * GeminiInquiryService
 *
 * Provides Gemini AI-Powered Taxpayer Inquiry Assistance (English & Tagalog)
 * grounded with live MySQL database context (RAG) from:
 *  - tr_tax_assessments (RPT & Business Tax assessments, surcharges, penalties)
 *  - tr_market_stalls (Public market stall occupancy & rental rates)
 *  - tr_collections (Official receipts & itemized payments)
 *  - tr_online_payments (Citizen online payment status)
 *  - tr_funds (Treasury fund summary for staff copilot queries)
 *
 * Also persists all inquiries into `tr_ai_inquiries` for audit and analytics.
 */
class GeminiInquiryService
{
    private $db;
    private ?PDO $pdo = null;
    private string $apiKey = '';
    private string $model = 'gemini-2.0-flash';

    public function __construct($db = null)
    {
        $this->db = $db;
        if ($db && method_exists($db, 'getPdo')) {
            $this->pdo = $db->getPdo();
        } elseif ($db instanceof PDO) {
            $this->pdo = $db;
        }

        $this->apiKey = trim((string) (
            getenv('GEMINI_API_KEY')
            ?: ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? '')
        ));
        $envModel = trim((string) (
            getenv('GEMINI_MODEL')
            ?: ($_ENV['GEMINI_MODEL'] ?? $_SERVER['GEMINI_MODEL'] ?? '')
        ));
        if ($envModel !== '') {
            $this->model = $envModel;
        }

        $this->ensureTable();
    }

    /**
     * Ensure `tr_ai_inquiries` table exists and has initial sample logs if empty.
     */
    public function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `tr_ai_inquiries` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `session_id` VARCHAR(100) DEFAULT NULL,
                  `citizen_id` VARCHAR(100) DEFAULT NULL,
                  `sender_name` VARCHAR(255) DEFAULT 'Citizen Taxpayer',
                  `sender_role` ENUM('citizen', 'employee') NOT NULL DEFAULT 'citizen',
                  `language` VARCHAR(20) NOT NULL DEFAULT 'auto',
                  `category` VARCHAR(100) NOT NULL DEFAULT 'General Tax Inquiry',
                  `question` TEXT NOT NULL,
                  `answer` LONGTEXT NOT NULL,
                  `matched_reference` VARCHAR(100) DEFAULT NULL,
                  `model_used` VARCHAR(100) NOT NULL DEFAULT 'gemini-2.0-flash',
                  `response_ms` INT NOT NULL DEFAULT 0,
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  INDEX `idx_ai_inq_role` (`sender_role`),
                  INDEX `idx_ai_inq_cat` (`category`),
                  INDEX `idx_ai_inq_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Check if settings table exists for optional UI-stored Gemini API key
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `tr_ai_settings` (
                  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                  `setting_value` TEXT DEFAULT NULL,
                  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            if ($this->apiKey === '') {
                $stmtKey = $this->pdo->prepare("SELECT setting_value FROM tr_ai_settings WHERE setting_key = 'GEMINI_API_KEY' LIMIT 1");
                $stmtKey->execute();
                $storedKey = trim((string) ($stmtKey->fetchColumn() ?: ''));
                if ($storedKey !== '') {
                    $this->apiKey = $storedKey;
                }
            }

            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM `tr_ai_inquiries`")->fetchColumn();
            if ($count === 0) {
                $seedStmt = $this->pdo->prepare("
                    INSERT INTO `tr_ai_inquiries`
                        (`session_id`, `citizen_id`, `sender_name`, `sender_role`, `language`, `category`, `question`, `answer`, `matched_reference`, `model_used`, `response_ms`)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $seeds = [
                    [
                        'seed-session-1',
                        'CIT-2026-1001',
                        'Roberto Dela Cruz',
                        'citizen',
                        'tl',
                        'Real Property Tax (RPT)',
                        'Magkano po ang babayaran ko sa RPT-2026-001 at bakit may surcharge?',
                        "Magandang araw po, G. Roberto Dela Cruz! Ayon sa live record ng Caloocan City Treasury para sa Assessment **#RPT-2026-001**:\n\n- **Basic Real Property Tax:** ₱12,500.00\n- **Surcharge (25% Late Payment):** ₱3,125.00\n- **Interest / Penalty (2% per month):** ₱750.00\n- **Kabuuang Halaga (Total Amount Due):** **₱16,375.00**\n- **Status:** Delinquent (Due Date: March 31, 2026)\n\nAlinsunod sa Local Government Code (RA 7160 Sec. 255), ang hindi nabayarang amilyar sa takdang petsa ay napapatawan ng **25% surcharge** at **2% interes kada buwan**.",
                        'RPT-2026-001',
                        'gemini-2.0-flash (RAG)',
                        420
                    ],
                    [
                        'seed-session-2',
                        'CIT-2026-1042',
                        'Grace Villanueva',
                        'citizen',
                        'en',
                        'Business Tax & Permits',
                        'What are the requirements and steps for Business Permit renewal in Caloocan City, and can I check BIZ-2026-001?',
                        "Hello **Grace Villanueva Enterprises**! Based on live Treasury records, your assessment **#BIZ-2026-001** (Business Tax & Mayor's Permit for Q1–Q2 2026) has the following breakdown:\n\n- **Basic Tax / Principal:** ₱18,400.00\n- **Surcharge (25%):** ₱4,600.00\n- **Interest (2%/mo):** ₱1,104.00\n- **Total Due:** **₱24,104.00** (Status: *Delinquent*, Due: Jan 20, 2026)\n\n### Requirements for Business Permit Renewal:\n1. Previous Year's Mayor's Permit & Official Receipt\n2. Audited Financial Statements / Sworn Declaration of Gross Sales\n3. Barangay Business Clearance\n4. Fire Safety Inspection Certificate (FSIC) & Sanitary Permit\n5. Community Tax Certificate (Cedula)",
                        'BIZ-2026-001',
                        'gemini-2.0-flash (RAG)',
                        385
                    ],
                    [
                        'seed-session-3',
                        'CIT-2026-1088',
                        'Lorna Bautista',
                        'citizen',
                        'tl',
                        'Market Stall Rental',
                        'Kailan po ang due date ng bayad sa market stall A-03 para hindi magka-penalty?',
                        "Para sa inyong pwesto na **Stall #A-03 (Wet Market - Fish, Lorna Bautista)**:\n\n- **Monthly Rental Fee:** **₱1,500.00**\n- **Occupancy Status:** Occupied\n- **Cutoff Date:** Tuwing **ika-20 ng buwan (20th of the month)**\n\nKapag lumagpas sa ika-20 ng buwan, awtomatikong madadagdagan ng **25% late surcharge (₱375.00)** kaya magiging **₱1,875.00** ang babayaran.",
                        'A-03',
                        'gemini-2.0-flash (RAG)',
                        310
                    ],
                ];
                foreach ($seeds as $s) {
                    $seedStmt->execute($s);
                }
            }
        } catch (\Throwable $e) {
            error_log('GeminiInquiryService::ensureTable error: ' . $e->getMessage());
        }
    }

    /**
     * Save or update the Gemini API key in `tr_ai_settings` (and runtime).
     */
    public function saveApiKey(string $key): bool
    {
        $this->apiKey = trim($key);
        if (!$this->pdo) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `tr_ai_settings` (`setting_key`, `setting_value`)
                VALUES ('GEMINI_API_KEY', ?)
                ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
            ");
            return $stmt->execute([$this->apiKey]);
        } catch (\Throwable $e) {
            error_log('GeminiInquiryService::saveApiKey error: ' . $e->getMessage());
            return false;
        }
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * Main entry point to answer a taxpayer or treasury staff inquiry.
     */
    public function handleInquiry(array $payload): array
    {
        $startTime = microtime(true);

        $question    = trim((string) ($payload['question'] ?? $payload['message'] ?? ''));
        $senderName  = trim((string) ($payload['sender_name'] ?? $payload['taxpayer_name'] ?? 'Citizen Taxpayer'));
        $senderRole  = in_array(($payload['sender_role'] ?? 'citizen'), ['citizen', 'employee'], true)
            ? $payload['sender_role']
            : 'citizen';
        $citizenId   = trim((string) ($payload['citizen_id'] ?? $payload['citizen_user_id'] ?? ($_SESSION['citizen_id'] ?? '')));
        $accountRef  = trim((string) ($payload['account_number'] ?? $payload['reference_no'] ?? ''));
        $language    = strtolower(trim((string) ($payload['language'] ?? 'auto')));
        $history     = is_array($payload['history'] ?? null) ? $payload['history'] : [];
        $sessionId   = trim((string) ($payload['session_id'] ?? session_id() ?: 'anon'));

        if ($question === '') {
            return [
                'success' => false,
                'error'   => 'Please enter a question or tax inquiry.',
            ];
        }

        // Detect category and extract live database context (RAG)
        $category = $this->detectCategory($question);
        $ragContext = $this->buildLiveDatabaseContext($question, $accountRef, $citizenId, $senderName);

        // Call live Gemini REST API if key is configured, else use live-DB RAG intelligent engine
        $modelUsed = $this->model;
        $answer = null;

        if ($this->apiKey !== '') {
            $geminiResult = $this->callGeminiApi($question, $ragContext, $language, $senderRole, $history);
            if ($geminiResult['success'] && !empty($geminiResult['text'])) {
                $answer = $geminiResult['text'];
                $modelUsed = $geminiResult['model'] . ' (Live Gemini + RAG)';
            }
        }

        if ($answer === null) {
            $answer = $this->generateLiveRagResponse($question, $ragContext, $language, $category);
            $modelUsed = $this->apiKey !== ''
                ? 'Caloocan Treasury AI Engine (Gemini Fallback + Live DB RAG)'
                : 'Caloocan Treasury AI Engine (Live DB RAG)';
        }

        $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

        // Log inquiry to database
        $inquiryId = $this->logInquiry([
            'session_id'        => $sessionId,
            'citizen_id'        => $citizenId ?: null,
            'sender_name'       => $senderName ?: 'Citizen Taxpayer',
            'sender_role'       => $senderRole,
            'language'          => $language,
            'category'          => $category,
            'question'          => $question,
            'answer'            => $answer,
            'matched_reference' => $ragContext['matched_reference'] ?? null,
            'model_used'        => $modelUsed,
            'response_ms'       => $elapsedMs,
        ]);

        return [
            'success'           => true,
            'inquiry_id'        => $inquiryId,
            'category'          => $category,
            'question'          => $question,
            'answer'            => $answer,
            'matched_reference' => $ragContext['matched_reference'] ?? null,
            'matched_record'    => $ragContext['matched_record'] ?? null,
            'model_used'        => $modelUsed,
            'response_ms'       => $elapsedMs,
            'timestamp'         => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Call Google Generative Language REST API (`generateContent`).
     */
    private function callGeminiApi(string $question, array $ragContext, string $language, string $senderRole, array $history): array
    {
        $modelsToTry = array_values(array_unique([$this->model, 'gemini-2.0-flash', 'gemini-1.5-flash']));

        $langInstruction = match ($language) {
            'tl', 'tagalog', 'filipino' => 'Respond primarily in polite, clear Tagalog / Filipino (using standard English financial terms like Official Receipt, Surcharge, Basic Tax where helpful).',
            'en', 'english'             => 'Respond in clear, professional English.',
            default                     => 'Detect the language of the user\'s question (English, Tagalog, or Taglish) and respond naturally in the same language.',
        };

        $systemInstruction = "You are the official **Caloocan City Hall Treasury & Revenue Collection AI Assistant (Civentral AI)**.\n"
            . "Your role is to assist citizens/taxpayers and Treasury staff with accurate, transparent information on:\n"
            . "1. **Real Property Tax (RPT / Amilyar):** Basic RPT + Special Education Fund (SEF). Deadline is March 31 (annual) or quarterly (Mar 31, Jun 30, Sep 30, Dec 31). Late payments incur a **25% statutory surcharge** + **2% monthly interest** (up to 36 months) under RA 7160 (Local Government Code Sec. 255).\n"
            . "2. **Business Tax & Mayor's Permit Renewal:** Deadline is January 20 each year (or quarterly on Jan 20, Apr 20, Jul 20, Oct 20). Late renewal incurs a **25% surcharge** on basic tax plus **2% monthly interest**.\n"
            . "3. **Public Market Stall Rental:** Monthly rental is due on or before the **20th of every month**. Payments after the 20th incur a **25% late surcharge**.\n"
            . "4. **Community Tax Certificate (Cedula) & Miscellaneous Fees:** Basic Cedula is ₱5.00 + ₱1.00 for every ₱1,000.00 of gross earnings/income.\n"
            . "5. **Online Payment Channels:** Citizens can pay online via **GCash, Maya, Credit/Debit Card, or Online Banking** through the Civentral Citizen Portal (powered by PayMongo with a 1% convenience fee) and receive an electronic Official Receipt (OR).\n\n"
            . "### LIVE DATABASE CONTEXT (Ground Truth from Caloocan City Treasury MySQL Database):\n"
            . $ragContext['context_text'] . "\n\n"
            . "### Formatting & Behavior Rules:\n"
            . "- {$langInstruction}\n"
            . "- Always cite exact Peso amounts (₱) and reference numbers from the LIVE DATABASE CONTEXT above whenever a matching assessment, stall, or receipt is found.\n"
            . "- Use clean Markdown formatting (bullet points, bold key figures) so it is easy to read.\n"
            . "- Keep answers concise, helpful, and polite.";

        $contents = [];
        foreach (array_slice($history, -6) as $turn) {
            $role = (($turn['role'] ?? 'user') === 'assistant' || ($turn['role'] ?? '') === 'model') ? 'model' : 'user';
            $text = trim((string) ($turn['content'] ?? $turn['text'] ?? ''));
            if ($text !== '') {
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $question]],
        ];

        $requestBody = json_encode([
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 700,
            ],
        ]);

        foreach ($modelsToTry as $modelName) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                . rawurlencode($modelName)
                . ':generateContent?key=' . rawurlencode($this->apiKey);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $requestBody,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($raw, true);
                $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return [
                        'success' => true,
                        'text'    => trim($text),
                        'model'   => $modelName,
                    ];
                }
            }
        }

        return ['success' => false];
    }

    /**
     * Query the live database (`tr_tax_assessments`, `tr_market_stalls`, `tr_collections`, `tr_funds`)
     * to provide real-time RAG grounding for both Gemini and the local fallback engine.
     */
    private function buildLiveDatabaseContext(string $question, string $accountRef, string $citizenId, string $senderName): array
    {
        $matchedRef = null;
        $matchedRecord = null;
        $lines = [];

        if (!$this->pdo) {
            return [
                'context_text'      => 'Database connection unavailable.',
                'matched_reference' => null,
                'matched_record'    => null,
            ];
        }

        try {
            $repo = new \App\Repositories\TreasuryRepository($this->db);
            $assessments = $repo->getAllTaxAssessments();
            $stalls = $repo->getAllMarketStalls();

            // 1. Check if question or accountRef mentions an Assessment No (e.g. RPT-2026-001, BIZ-2026-001, TA-2026-102)
            $combined = $question . ' ' . $accountRef . ' ' . $senderName;

            foreach ($assessments as $ass) {
                $assNo = (string) ($ass['assessment_no'] ?? '');
                $tName = (string) ($ass['taxpayer_name'] ?? '');
                $tdNo  = (string) ($ass['td_or_permit_no'] ?? '');

                if (
                    ($assNo !== '' && stripos($combined, $assNo) !== false)
                    || ($tdNo !== '' && stripos($combined, $tdNo) !== false)
                    || ($tName !== '' && strlen($tName) > 4 && stripos($combined, $tName) !== false)
                ) {
                    $matchedRef = $assNo;
                    $matchedRecord = ['type' => 'tax_assessment', 'data' => $ass];
                    break;
                }
            }

            // 2. Check Market Stalls (e.g. A-01, A-03, B-01, MS-A01 or vendor name)
            if (!$matchedRecord) {
                foreach ($stalls as $st) {
                    $stNo = (string) ($st['stall_number'] ?? '');
                    $vName = (string) ($st['vendor_name'] ?? '');
                    if (
                        ($stNo !== '' && preg_match('/\b' . preg_quote($stNo, '/') . '\b/i', $combined))
                        || ($vName !== '' && strlen($vName) > 4 && stripos($combined, $vName) !== false)
                    ) {
                        $matchedRef = $stNo;
                        $matchedRecord = ['type' => 'market_stall', 'data' => $st];
                        break;
                    }
                }
            }

            // 3. Check Official Receipts (`tr_collections`) if OR number is mentioned
            if (!$matchedRecord && preg_match('/(OR-[A-Z0-9\-]+)/i', $combined, $m)) {
                $orCode = strtoupper($m[1]);
                $stmtOr = $this->pdo->prepare("SELECT * FROM `tr_collections` WHERE or_number = ? LIMIT 1");
                $stmtOr->execute([$orCode]);
                $orRow = $stmtOr->fetch(PDO::FETCH_ASSOC);
                if ($orRow) {
                    $matchedRef = $orCode;
                    $matchedRecord = ['type' => 'collection', 'data' => $orRow];
                }
            }

            // Build text summary for Gemini RAG prompt
            if ($matchedRecord) {
                $lines[] = "MATCHED TAXPAYER RECORD (" . strtoupper($matchedRecord['type']) . "): " . json_encode($matchedRecord['data'], JSON_UNESCAPED_UNICODE);
            }

            // Include summary of active Tax Assessments
            $assSummaries = [];
            foreach (array_slice($assessments, 0, 10) as $a) {
                $basicAmt = (float) ($a['basic_amount'] ?? $a['tax_due'] ?? 0);
                $surAmt   = (float) ($a['surcharge_amount'] ?? 0);
                $penAmt   = (float) ($a['penalty_amount'] ?? 0);
                $tot      = $basicAmt + $surAmt + $penAmt;
                $assSummaries[] = sprintf(
                    "%s (%s - %s): Basic=₱%s, Surcharge=₱%s, Interest=₱%s, Total=₱%s, Due=%s, Status=%s",
                    $a['assessment_no'] ?? '',
                    $a['taxpayer_name'] ?? '',
                    $a['tax_type'] ?? '',
                    number_format($basicAmt, 2),
                    number_format($surAmt, 2),
                    number_format($penAmt, 2),
                    number_format($tot, 2),
                    $a['due_date'] ?? 'N/A',
                    strtoupper((string) ($a['status'] ?? 'unpaid'))
                );
            }
            if ($assSummaries) {
                $lines[] = "ACTIVE TAX ASSESSMENTS IN DATABASE:\n- " . implode("\n- ", $assSummaries);
            }

            // Include summary of Market Stalls
            $stallSummaries = [];
            foreach ($stalls as $s) {
                $stallSummaries[] = sprintf(
                    "Stall %s (%s): Vendor=%s, MonthlyRate=₱%s, Status=%s, LastPaid=%s",
                    $s['stall_number'],
                    $s['section'],
                    $s['vendor_name'] ?: 'VACANT',
                    number_format((float) ($s['monthly_rate'] ?? $s['monthly_fee'] ?? 0), 2),
                    strtoupper((string) ($s['occupancy_status'] ?? $s['status'] ?? 'vacant')),
                    $s['last_payment_date'] ?: 'None'
                );
            }
            if ($stallSummaries) {
                $lines[] = "PUBLIC MARKET STALLS IN DATABASE:\n- " . implode("\n- ", $stallSummaries);
            }

            // Include Fund Balances summary
            $funds = $this->pdo->query("SELECT code, name, balance, opening_balance FROM `tr_funds` ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
            if ($funds) {
                $fList = array_map(fn($f) => "{$f['code']} ({$f['name']}): ₱" . number_format((float) $f['balance'], 2), $funds);
                $lines[] = "TREASURY FUNDS SUMMARY: " . implode(' | ', $fList);
            }
        } catch (\Throwable $e) {
            error_log('GeminiInquiryService::buildLiveDatabaseContext error: ' . $e->getMessage());
        }

        return [
            'context_text'      => implode("\n\n", $lines),
            'matched_reference' => $matchedRef,
            'matched_record'    => $matchedRecord,
            'assessments'       => $assessments ?? [],
            'stalls'            => $stalls ?? [],
            'funds'             => $funds ?? [],
        ];
    }

    /**
     * Dynamic Live-DB RAG Response Generator (used when GEMINI_API_KEY is not yet set or offline).
     * Queries the live database records and computes exact tax breakdowns in English or Tagalog.
     */
    private function generateLiveRagResponse(string $question, array $ragContext, string $language, string $category): string
    {
        $qLower = strtolower($question);
        $isTagalog = $language === 'tl'
            || preg_match('/\b(magkano|paano|kailan|saan|ano|bakit|po|ba|bayad|amilyar|pwesto|negosyo|cedula|penalty|surcharge)\b/i', $question);

        $matched = $ragContext['matched_record'] ?? null;

        // 1. If a specific Tax Assessment was matched in the live database
        if ($matched && $matched['type'] === 'tax_assessment') {
            $a     = $matched['data'];
            $basic = (float) ($a['basic_amount'] ?? 0);
            $sur   = (float) ($a['surcharge_amount'] ?? 0);
            $pen   = (float) ($a['penalty_amount'] ?? 0);
            $total = $basic + $sur + $pen;
            $status = strtoupper((string) ($a['status'] ?? 'UNPAID'));

            if ($isTagalog) {
                return "Narito po ang live record mula sa **Caloocan City Treasury Database** para sa Assessment **#{$a['assessment_no']}**:\n\n"
                    . "- **Pangalan ng Taxpayer:** {$a['taxpayer_name']}\n"
                    . "- **Uri ng Buwis:** {$a['tax_type']} ({$a['period_covered']})\n"
                    . "- **TD / Permit No.:** `{$a['td_or_permit_no']}`\n"
                    . "- **Basic Tax / Principal:** ₱" . number_format($basic, 2) . "\n"
                    . "- **Surcharge (25% Late Penalty):** ₱" . number_format($sur, 2) . "\n"
                    . "- **Interest (2% per Month):** ₱" . number_format($pen, 2) . "\n"
                    . "- **Kabuuang Babayaran (Total Due):** **₱" . number_format($total, 2) . "**\n"
                    . "- **Due Date:** {$a['due_date']} · **Status:** **{$status}**\n\n"
                    . ($status === 'PAID'
                        ? "✅ Ang account na ito ay **bayad na (Settled)**."
                        : "💡 Maaari po kayong magbayad online gamit ang **GCash, Maya, o Card** sa Citizen Portal o direkta sa Treasury Collection Terminal.");
            }

            return "Here is the live record from the **Caloocan City Treasury Database** for Assessment **#{$a['assessment_no']}**:\n\n"
                . "- **Taxpayer Name:** {$a['taxpayer_name']}\n"
                . "- **Tax Type & Period:** {$a['tax_type']} ({$a['period_covered']})\n"
                . "- **TD / Permit No.:** `{$a['td_or_permit_no']}`\n"
                . "- **Basic Tax / Principal:** ₱" . number_format($basic, 2) . "\n"
                . "- **Surcharge (25% Statutory):** ₱" . number_format($sur, 2) . "\n"
                . "- **Interest (2% Monthly):** ₱" . number_format($pen, 2) . "\n"
                . "- **Total Amount Due:** **₱" . number_format($total, 2) . "**\n"
                . "- **Due Date:** {$a['due_date']} · **Status:** **{$status}**\n\n"
                . ($status === 'PAID'
                    ? "✅ This assessment is already **PAID / Settled**."
                    : "💡 You may settle this online via **GCash, Maya, or Debit/Credit Card** through the Citizen Payment Portal or at the City Treasurer's Office.");
        }

        // 2. If a specific Market Stall was matched in the live database
        if ($matched && $matched['type'] === 'market_stall') {
            $s    = $matched['data'];
            $rate = (float) ($s['monthly_rate'] ?? $s['monthly_fee'] ?? 0);
            $occ  = strtoupper((string) ($s['occupancy_status'] ?? $s['status'] ?? 'VACANT'));
            $lateSurcharge = round($rate * 0.25, 2);
            $lateTotal     = $rate + $lateSurcharge;

            if ($isTagalog) {
                return "Narito po ang live status ng **Market Stall #{$s['stall_number']} ({$s['section']})**:\n\n"
                    . "- **Nakatalagang Vendor:** " . ($s['vendor_name'] ?: 'Vacant / Available') . "\n"
                    . "- **Occupancy Status:** **{$occ}**\n"
                    . "- **Buwanang Upa (Monthly Rental):** **₱" . number_format($rate, 2) . "**\n"
                    . "- **Huling Petsa ng Bayad:** " . ($s['last_payment_date'] ?: 'Wala pang record ngayong buwan') . "\n"
                    . "- **Cutoff Date:** Tuwing **ika-20 ng buwan** (Kapag lumagpas, may **25% surcharge** na ₱" . number_format($lateSurcharge, 2) . " → Kabuuang **₱" . number_format($lateTotal, 2) . "**).";
            }

            return "Here is the live ledger status for **Market Stall #{$s['stall_number']} ({$s['section']})**:\n\n"
                . "- **Assigned Vendor:** " . ($s['vendor_name'] ?: 'Vacant / Available for Lease') . "\n"
                . "- **Occupancy Status:** **{$occ}**\n"
                . "- **Monthly Rental Rate:** **₱" . number_format($rate, 2) . "**\n"
                . "- **Last Payment Date:** " . ($s['last_payment_date'] ?: 'No recent payment recorded') . "\n"
                . "- **Payment Cutoff:** **20th of the month** (Late payments after the 20th incur a **25% surcharge** of ₱" . number_format($lateSurcharge, 2) . ", totaling **₱" . number_format($lateTotal, 2) . "**).";
        }

        // 3. If an Official Receipt (OR) was matched
        if ($matched && $matched['type'] === 'collection') {
            $c = $matched['data'];
            return "Official Receipt **#{$c['or_number']}** was found in the live Treasury Ledger:\n\n"
                . "- **Payer Name:** {$c['payer_name']}\n"
                . "- **Revenue Source:** {$c['revenue_source']} (Fund: `{$c['fund_code']}`)\n"
                . "- **Basic Amount:** ₱" . number_format((float) ($c['basic_amount'] ?? $c['amount']), 2) . "\n"
                . "- **Surcharge / Penalty:** ₱" . number_format((float) ($c['surcharge_amount'] ?? 0) + (float) ($c['penalty_amount'] ?? 0), 2) . "\n"
                . "- **Total Paid:** **₱" . number_format((float) $c['amount'], 2) . "**\n"
                . "- **Payment Mode:** " . strtoupper((string) $c['payment_mode']) . " · **Date:** {$c['created_at']}";
        }

        // 4. If user includes a custom peso amount to compute surcharge/penalty (e.g., "compute surcharge for 15000")
        if (preg_match('/(\d[\d,]*(\.\d+)?)/', str_replace(',', '', $question), $numMatch)) {
            $val = (float) $numMatch[1];
            if ($val >= 100 && (strpos($qLower, 'compute') !== false || strpos($qLower, 'surcharge') !== false || strpos($qLower, 'penalty') !== false || strpos($qLower, 'kwenta') !== false)) {
                $sur = round($val * 0.25, 2);
                $pen = round($val * 0.02, 2);
                $tot = $val + $sur + $pen;
                return "### Statutory Tax Breakdown Computation (RA 7160 / Caloocan Revenue Code)\n\n"
                    . "- **Basic Tax / Principal:** ₱" . number_format($val, 2) . "\n"
                    . "- **25% Statutory Surcharge (One-Time Late Penalty):** ₱" . number_format($sur, 2) . "\n"
                    . "- **2% Monthly Interest (1 Month):** ₱" . number_format($pen, 2) . "\n"
                    . "- **Total Amount Due:** **₱" . number_format($tot, 2) . "**\n\n"
                    . "*Note: Under Section 255 of the Local Government Code, the 2% monthly interest accumulates for each month of delinquency up to a maximum of 36 months (72%).*";
            }
        }

        // 5. Category-based live data + ordinance response
        switch ($category) {
            case 'Real Property Tax (RPT)':
                $rptList = array_filter($ragContext['assessments'] ?? [], fn($a) => stripos((string) $a['tax_type'], 'Property') !== false);
                $sampleRefs = implode(', ', array_map(fn($a) => "`{$a['assessment_no']}` ({$a['taxpayer_name']})", array_slice($rptList, 0, 3)));
                if ($isTagalog) {
                    return "### Real Property Tax (Amilyar / RPT) — Caloocan City Treasury\n\n"
                        . "1. **Takdang Petsa ng Bayad (Deadlines):**\n"
                        . "   - **Taunan (Full Payment):** Hanggang **March 31** (may diskwento kapag maagang nagbayad).\n"
                        . "   - **Quarterly Installments:** Q1 (Mar 31), Q2 (Jun 30), Q3 (Sep 30), Q4 (Dec 31).\n"
                        . "2. **Komputasyon ng Penalty (RA 7160 Sec. 255):**\n"
                        . "   - **25% Surcharge** sa hindi nabayarang Basic RPT + SEF.\n"
                        . "   - **2% Interest kada buwan** ng pagkaantala (hanggang 36 buwan).\n"
                        . "3. **Live Assessment Lookup:** I-type lamang ang inyong Assessment No. (hal. {$sampleRefs}) upang makita ang eksaktong breakdown.";
                }
                return "### Real Property Tax (RPT) Guidelines & Computation — Caloocan City\n\n"
                    . "1. **Payment Deadlines:**\n"
                    . "   - **Annual Payment:** On or before **March 31**.\n"
                    . "   - **Quarterly Installments:** Mar 31 (Q1), Jun 30 (Q2), Sep 30 (Q3), Dec 31 (Q4).\n"
                    . "2. **Delinquency Penalties (RA 7160 Sec. 255):**\n"
                    . "   - **25% Surcharge** on the unpaid Basic Tax + Special Education Fund (SEF).\n"
                    . "   - **2% Monthly Interest** on the unpaid amount (plus surcharge) until fully paid (maximum of 36 months).\n"
                    . "3. **Check Your Live Assessment:** Enter your Assessment No. in the chat (e.g., {$sampleRefs}) for an instant itemized breakdown.";

            case 'Business Tax & Permits':
                if ($isTagalog) {
                    return "### Business Tax & Mayor's Permit Renewal — Caloocan City\n\n"
                        . "- **Deadline ng Renewal:** Tuwing **January 1 hanggang January 20** bawat taon (o quarterly: Jan 20, Apr 20, Jul 20, Oct 20).\n"
                        . "- **Mga Kailangang Dokumento:**\n"
                        . "  1. Nakaraang Mayor's Permit at Official Receipt\n"
                        . "  2. Sworn Declaration of Gross Sales / Receipts o Financial Statement\n"
                        . "  3. Barangay Business Clearance, Sanitary Permit, at Fire Safety Inspection Certificate (FSIC)\n"
                        . "  4. Community Tax Certificate (Cedula)\n"
                        . "- **Late Renewal Penalty:** **25% Surcharge** + **2% interes kada buwan**. Maaari ninyong i-type ang `BIZ-2026-001` o `BIZ-2026-002` upang tingnan ang live assessment.";
                }
                return "### Business Tax & Mayor's Permit Renewal — Caloocan City\n\n"
                    . "- **Renewal Deadline:** **January 20** annually (or quarterly on Jan 20, Apr 20, Jul 20, Oct 20).\n"
                    . "- **Documentary Requirements:**\n"
                    . "  1. Previous Year's Mayor's Permit & Official Receipt\n"
                    . "  2. Audited Financial Statements / Sworn Declaration of Gross Receipts\n"
                    . "  3. Barangay Clearance, Sanitary Permit, and Fire Safety Inspection Certificate (FSIC)\n"
                    . "  4. Community Tax Certificate (Cedula)\n"
                    . "- **Late Penalties:** **25% statutory surcharge** + **2% monthly interest**. Type an assessment number such as `BIZ-2026-001` or `BIZ-2026-002` to inspect a live account.";

            case 'Market Stall Rental':
                $stalls = $ragContext['stalls'] ?? [];
                $vacant = array_filter($stalls, fn($s) => strtolower((string) ($s['occupancy_status'] ?? $s['status'] ?? '')) === 'vacant');
                $vacantList = implode(', ', array_map(fn($s) => "`{$s['stall_number']}` ({$s['section']} - ₱" . number_format((float) ($s['monthly_rate'] ?? 0), 2) . "/mo)", $vacant));
                return "### Caloocan Public Market Stall Rental & Occupancy\n\n"
                    . "- **Monthly Cutoff Date:** On or before the **20th of every month**.\n"
                    . "- **Late Payment Surcharge:** **25% surcharge** is automatically added after the 20th.\n"
                    . "- **Total Stalls in Ledger:** **" . count($stalls) . " stalls** (" . (count($stalls) - count($vacant)) . " occupied, " . count($vacant) . " vacant).\n"
                    . "- **Currently Vacant Stalls Available for Lease:** " . ($vacantList ?: 'None at the moment') . "\n"
                    . "- *Tip:* Type a stall number like `A-01`, `A-03`, or `C-02` to check its vendor and payment status.";

            case 'Online Payment & Receipts':
                return "### Online Tax & Fee Payment (PayMongo — GCash, Maya, Card)\n\n"
                    . "1. **Supported Channels:** GCash, Maya, Credit/Debit Card, and Online Banking via the **Civentral Citizen Portal**.\n"
                    . "2. **Convenience Fee:** 1% gateway processing fee.\n"
                    . "3. **Automatic Official Receipt (OR):** Once paid, PayMongo notifies the Treasury webhook (`api/webhooks/paymongo.php`), automatically issues an Official Receipt (`OR-YYYY-...`), and records `+ Money In` in the corresponding Treasury Fund Ledger.";

            default:
                if ($isTagalog) {
                    return "Magandang araw po! Ako ang **Caloocan City Treasury Gemini AI Assistant**.\n\n"
                        . "Maaari po ninyo akong tanungin tungkol sa:\n"
                        . "- **Real Property Tax (Amilyar):** Hal. *\"Magkano ang babayaran sa RPT-2026-001?\"*\n"
                        . "- **Business Tax & Mayor's Permit:** Hal. *\"Ano ang requirements sa Business Permit renewal at status ng BIZ-2026-001?\"*\n"
                        . "- **Public Market Stall Rental:** Hal. *\"Ano ang status ng Stall A-03 o anong stall ang bakante?\"*\n"
                        . "- **Komputasyon ng Surcharge at Penalty:** Hal. *\"Compute surcharge for 15000\"*";
                }
                return "Hello! I am the **Caloocan City Hall Treasury Gemini AI Assistant**.\n\n"
                    . "I am connected to the live Treasury Database. You can ask me in **English or Tagalog** about:\n"
                    . "- **Live Tax Assessment Lookup:** Try asking *\"Check assessment RPT-2026-001\"* or *\"Status of BIZ-2026-001\"*\n"
                    . "- **Market Stall Occupancy & Rates:** Try *\"Check market stall A-03\"* or *\"Which market stalls are vacant?\"*\n"
                    . "- **Tax & Surcharge Computations:** Try *\"Compute surcharge and interest for 15000\"*\n"
                    . "- **Business Permit Requirements & Online Payments (GCash / Maya)**";
        }
    }

    public function detectCategory(string $question): string
    {
        $q = strtolower($question);
        if (preg_match('/(rpt|real property|amilyar|land tax|property tax|td-|arp)/i', $q)) {
            return 'Real Property Tax (RPT)';
        }
        if (preg_match('/(business|mayor|permit|gross|renewal|biz-)/i', $q)) {
            return 'Business Tax & Permits';
        }
        if (preg_match('/(market|stall|vendor|pwesto|palengke|section|[a-d]-0\d)/i', $q)) {
            return 'Market Stall Rental';
        }
        if (preg_match('/(gcash|maya|online|paymongo|receipt|or-|checkout|card)/i', $q)) {
            return 'Online Payment & Receipts';
        }
        if (preg_match('/(cedula|community tax|ctc|clearance)/i', $q)) {
            return 'Cedula & Miscellaneous Fees';
        }
        return 'General Tax Inquiry';
    }

    private function logInquiry(array $data): int
    {
        if (!$this->pdo) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `tr_ai_inquiries`
                    (`session_id`, `citizen_id`, `sender_name`, `sender_role`, `language`, `category`, `question`, `answer`, `matched_reference`, `model_used`, `response_ms`)
                VALUES
                    (:session_id, :citizen_id, :sender_name, :sender_role, :language, :category, :question, :answer, :matched_reference, :model_used, :response_ms)
            ");
            $stmt->execute([
                ':session_id'        => $data['session_id'],
                ':citizen_id'        => $data['citizen_id'],
                ':sender_name'       => $data['sender_name'],
                ':sender_role'       => $data['sender_role'],
                ':language'          => $data['language'],
                ':category'          => $data['category'],
                ':question'          => $data['question'],
                ':answer'            => $data['answer'],
                ':matched_reference' => $data['matched_reference'],
                ':model_used'        => $data['model_used'],
                ':response_ms'       => (int) $data['response_ms'],
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('GeminiInquiryService::logInquiry error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieve recent AI inquiries for the Treasury Admin dashboard.
     */
    public function getRecentInquiries(int $limit = 100, ?string $category = null): array
    {
        if (!$this->pdo) {
            return [];
        }
        try {
            if ($category && $category !== '') {
                $stmt = $this->pdo->prepare("SELECT * FROM `tr_ai_inquiries` WHERE category = ? ORDER BY id DESC LIMIT ?");
                $stmt->bindValue(1, $category, PDO::PARAM_STR);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $stmt = $this->pdo->prepare("SELECT * FROM `tr_ai_inquiries` ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

