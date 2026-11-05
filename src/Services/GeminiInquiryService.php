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

        // Detect category and extract live database context (RAG) with multi-turn history
        $category = $this->detectCategory($question, $history);
        $ragContext = $this->buildLiveDatabaseContext($question, $accountRef, $citizenId, $senderName, $history);

        // Call live Gemini REST API if key is configured, else use conversational Live-DB RAG engine
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
            $answer = $this->generateLiveRagResponse($question, $ragContext, $language, $category, $history);
            $modelUsed = $this->apiKey !== ''
                ? 'Caloocan Treasury AI Engine (Gemini Fallback + Live DB RAG)'
                : 'Caloocan Treasury AI Engine (Conversational Live DB RAG)';
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
     * Secondary Live Cloud Generative AI (OpenAI-compatible endpoint with RAG grounding)
     * used automatically when GEMINI_API_KEY is not yet configured in .env.
     */
    private function callCloudLlmWithRag(string $question, array $ragContext, string $language, array $history): array
    {
        // If a specific assessment, stall, or receipt reference was matched, let our deterministic
        // live-DB RAG formatter present the exact figures immediately unless it's an open-ended question.
        if (!empty($ragContext['matched_record']) && strlen($question) < 45) {
            return ['success' => false];
        }

        $langInstruction = match ($language) {
            'tl', 'tagalog', 'filipino' => 'Respond in clear, polite Tagalog / Filipino.',
            'en', 'english'             => 'Respond in clear, professional English.',
            default                     => 'Detect the language of the user (Tagalog, Taglish, or English) and respond in the exact same language.',
        };

        $systemPrompt = "You are the official Caloocan City Hall Treasury & Revenue Collection AI Assistant (Civentral AI).\n"
            . "Answer the citizen or staff question accurately and concisely based on Philippine Local Government Code (RA 7160), Caloocan City Revenue Code, and the LIVE DATABASE CONTEXT below.\n"
            . "- For NEW Business Name / Business Permit registration: Explain (1) Registering the Business Name first with DTI (Sole Proprietorship via bnrs.dti.gov.ph), SEC (Corporation/Partnership), or CDA; (2) Getting Barangay Business Clearance & Locational Clearance; (3) Filing at Caloocan BPLO (Business Permits & Licensing Office) + Fire Safety (FSIC) + Sanitary Permit + Cedula; (4) Paying the assessed initial tax & regulatory fees at the City Treasurer's Office or online via GCash/Maya.\n"
            . "- For Business Permit Renewal: Deadline is January 20 annually (or quarterly Jan 20, Apr 20, Jul 20, Oct 20). Late payment has a 25% surcharge + 2% monthly interest.\n"
            . "- For Real Property Tax (RPT / Amilyar): Annual deadline March 31, or quarterly (Mar 31, Jun 30, Sep 30, Dec 31). Late penalty is 25% surcharge + 2% monthly interest (max 36 months).\n"
            . "- For Public Market Stalls: Monthly rental cutoff is the 20th of each month (25% surcharge if late).\n"
            . "- {$langInstruction}\n\n"
            . "LIVE DATABASE CONTEXT:\n" . $ragContext['context_text'];

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (array_slice($history, -4) as $turn) {
            $role = (($turn['role'] ?? 'user') === 'assistant' || ($turn['role'] ?? '') === 'model') ? 'assistant' : 'user';
            $text = trim((string) ($turn['content'] ?? $turn['text'] ?? ''));
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $ch = curl_init('https://text.pollinations.ai/openai');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'model'       => 'openai',
                'messages'    => $messages,
                'temperature' => 0.3,
            ]),
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw !== false && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($raw, true);
            $content = $decoded['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') {
                return [
                    'success' => true,
                    'text'    => trim($content),
                ];
            }
        }

        return ['success' => false];
    }

    /**
     * Query the live database (`tr_tax_assessments`, `tr_market_stalls`, `tr_collections`, `tr_funds`)
     * to provide real-time RAG grounding for both Gemini and the local fallback engine.
     */
    private function buildLiveDatabaseContext(string $question, string $accountRef, string $citizenId, string $senderName, array $history = []): array
    {
        $matchedRef = null;
        $matchedRecord = null;
        $matchedFromHistory = false;
        $lines = [];

        if (!$this->pdo) {
            return [
                'context_text'         => 'Database connection unavailable.',
                'matched_reference'    => null,
                'matched_record'       => null,
                'matched_from_history' => false,
            ];
        }

        try {
            $repo = new \App\Repositories\TreasuryRepository($this->db);
            $assessments = $repo->getAllTaxAssessments();
            $stalls = $repo->getAllMarketStalls();

            // 1. Check if current question or accountRef mentions an Assessment No (e.g. RPT-2026-001, BIZ-2026-001, TA-2026-102)
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

            // 4. Multi-turn conversational memory: if current turn didn't explicitly repeat the code,
            // ONLY check the most recent USER message in conversation history (NEVER assistant messages,
            // because assistant replies contain example codes like BIZ-2026-001 / RPT-2026-001!).
            if (!$matchedRecord && !empty($history)) {
                $lastUserText = '';
                foreach (array_reverse($history) as $turn) {
                    if (($turn['role'] ?? '') === 'user') {
                        $lastUserText = (string) ($turn['content'] ?? $turn['text'] ?? '');
                        break;
                    }
                }
                if ($lastUserText !== '') {
                    foreach ($assessments as $ass) {
                        $assNo = (string) ($ass['assessment_no'] ?? '');
                        if ($assNo !== '' && stripos($lastUserText, $assNo) !== false) {
                            $matchedRef = $assNo;
                            $matchedRecord = ['type' => 'tax_assessment', 'data' => $ass];
                            $matchedFromHistory = true;
                            break;
                        }
                    }
                    if (!$matchedRecord) {
                        foreach ($stalls as $st) {
                            $stNo = (string) ($st['stall_number'] ?? '');
                            if ($stNo !== '' && preg_match('/\b' . preg_quote($stNo, '/') . '\b/i', $lastUserText)) {
                                $matchedRef = $stNo;
                                $matchedRecord = ['type' => 'market_stall', 'data' => $st];
                                $matchedFromHistory = true;
                                break;
                            }
                        }
                    }
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
            'context_text'         => implode("\n\n", $lines),
            'matched_reference'    => $matchedRef,
            'matched_record'       => $matchedRecord,
            'matched_from_history' => $matchedFromHistory,
            'assessments'          => $assessments ?? [],
            'stalls'               => $stalls ?? [],
            'funds'                => $funds ?? [],
        ];
    }

    /**
     * Dynamic Multi-Turn Conversational Live-DB RAG Response Generator
     * (used when GEMINI_API_KEY is not yet set or offline).
     * Tracks multi-turn conversation history, active taxpayer assessments/stalls, and follow-up intents
     * in English, Tagalog, and Taglish.
     */
    private function generateLiveRagResponse(string $question, array $ragContext, string $language, string $category, array $history = []): string
    {
        $qLower = strtolower(trim($question));
        $recentHistoryText = '';
        foreach (array_slice($history, -4) as $turn) {
            $recentHistoryText .= ' ' . ($turn['content'] ?? $turn['text'] ?? '');
        }
        $isTagalog = $language === 'tl'
            || (
                $language !== 'en'
                && (
                    preg_match('/\b(magkano|paano|pano|panu|kailan|kelan|saan|san|ano|anu|bakit|po|opo|ba|kung|gusto|ko|ako|mag|nag|ng|mga|bago|bagong|bayad|magbayad|babayaran|amilyar|pwesto|palengke|negosyo|cedula|sedula|sara|isara|kumuha|pwede|puwede|para|sa|yung|ung|yan|iyan|ito|gaano|katagal|salamat|maraming|oo|hindi|eh|pag|kapag)\b/i', $question)
                    || ($recentHistoryText !== '' && preg_match('/\b(po|opo|narito|hakbang|kabuuang|maaari)\b/i', $recentHistoryText))
                )
            );

        $matched            = $ragContext['matched_record'] ?? null;
        $matchedFromHistory = (bool) ($ragContext['matched_from_history'] ?? false);
        $hasHistory         = !empty($history);

        // 0. Conversational Gratitude / Acknowledgement ("salamat", "thank you", "ok gets ko na", "noted")
        if (preg_match('/^(thank you|thanks|maraming salamat|salamat|thank u|ty|ok|okay|gets|gets ko na|noted|sige|sige po|opo salamat|clear na)[\s!.,?po]*$/i', $qLower)) {
            if ($isTagalog) {
                return "Walang anuman po! Masaya akong makatulong sa inyong transaksyon sa **Caloocan City Treasurer's Office**.\n\n"
                    . "May iba pa po ba kayong gustong itanong tungkol sa inyong **Business Permit, Real Property Tax (Amilyar), Market Stall, o Online Payment**? I-type lang po ninyo rito at sasagutin ko agad!";
            }
            return "You're very welcome! I'm glad I could help with your **Caloocan City Treasury** inquiry.\n\n"
                . "Is there anything else you'd like to ask about **Business Permits, Real Property Tax (RPT), Market Stalls, or Online Payments**? Feel free to continue our chat anytime!";
        }

        // 0b. Pure Conversational Greeting ("hi", "hello", "good morning", "magandang araw", "pwede magtanong")
        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|magandang araw|magandang umaga|magandang hapon|kumusta|kamusta|pwede magtanong|pwede po magtanong|ask lang|tanong lang)[\s!.,?po]*$/i', $qLower)) {
            if ($isTagalog) {
                return "Magandang araw po! 👋 Ako ang inyong **Caloocan City Treasury Conversational AI Assistant**.\n\n"
                    . "Pwede tayong mag-usap nang tuloy-tuloy sa **Tagalog, Taglish, o English**! Ano po ang maitutulong ko sa inyo ngayon?\n"
                    . "- 🏢 **Pagpaparehistro ng Bagong Negosyo o Renewal ng Mayor's Permit**\n"
                    . "- 🏠 **Pag-check ng Real Property Tax (Amilyar) o Penalty** (hal. `RPT-2026-001`)\n"
                    . "- 🏪 **Public Market Stall Rental at Bakanteng Pwesto** (hal. `A-03`)\n"
                    . "- 💳 **Pagbabayad Online via GCash / Maya**\n\n"
                    . "*Ano po ang gusto ninyong unahin nating pag-usapan?*";
            }
            return "Hello and welcome! 👋 I am your **Caloocan City Hall Treasury Conversational AI Assistant**.\n\n"
                . "We can have a back-and-forth conversation in **English, Tagalog, or Taglish**! What would you like help with today?\n"
                . "- 🏢 **Registering a New Business Name or Renewing a Mayor's Permit**\n"
                . "- 🏠 **Checking a Real Property Tax (RPT) or Business Tax Assessment** (e.g., `RPT-2026-001`, `BIZ-2026-001`)\n"
                . "- 🏪 **Public Market Stall Availability & Monthly Rates** (e.g., `A-03`)\n"
                . "- 💳 **Paying Taxes Online via GCash, Maya, or Card**\n\n"
                . "*Tell me what you need and we'll go through it step by step!*";
        }

        // 1. If a specific Tax Assessment is active (either mentioned now OR remembered from previous chat turn!)
        if ($matched && $matched['type'] === 'tax_assessment') {
            $a      = $matched['data'];
            $basic  = (float) ($a['basic_amount'] ?? $a['tax_due'] ?? 0);
            $sur    = (float) ($a['surcharge_amount'] ?? 0);
            $pen    = (float) ($a['penalty_amount'] ?? 0);
            $total  = $basic + $sur + $pen;
            $status = strtoupper((string) ($a['status'] ?? 'UNPAID'));

            // Check if the user is asking a conversational FOLLOW-UP about this assessment
            // e.g. "bakit may surcharge/penalty?", "paano ko babayaran yan?", "pwede ba installment/discount?"
            if ($matchedFromHistory || preg_match('/(bakit|why|paano|pano|panu|how|online|gcash|maya|discount|installment|hulugan|bawas|waive|kelan|kailan|when)/i', $question)) {
                if (preg_match('/(bakit|why|surcharge|penalty|interest|patong|laki|mahal)/i', $question)) {
                    if ($isTagalog) {
                        return "Tungkol po sa tinatanong ninyong Assessment **#{$a['assessment_no']} ({$a['taxpayer_name']})**:\n\n"
                            . ($sur > 0 || $pen > 0
                                ? "Nagkaroon po ito ng karagdagang **₱" . number_format($sur, 2) . " (25% Surcharge)** at **₱" . number_format($pen, 2) . " (2% Monthly Interest)** dahil lumagpas na po ito sa takdang Due Date na **{$a['due_date']}**.\n\n"
                                  . "Alinsunod po sa **Section 255 ng Local Government Code (RA 7160)**, awtomatikong ipinapataw ang:\n"
                                  . "- **25% One-Time Surcharge** sa Basic Tax na ₱" . number_format($basic, 2) . "\n"
                                  . "- **2% Interest kada buwan** ng pagkaantala hanggang mabayaran.\n\n"
                                  . "💬 *Gusto po ba ninyong malaman kung paano ito babayaran online para hindi na madagdagan ang buwanang 2% interest?*"
                                : "Magandang balita po! **Wala pong surcharge o penalty (₱0.00)** ang Assessment **#{$a['assessment_no']}** dahil hindi pa ito lampas sa due date ({$a['due_date']}) o bayad na. Ang Basic Tax lamang na **₱" . number_format($basic, 2) . "** ang nakatala.\n\n"
                                  . "💬 *May iba pa po ba kayong gustong i-check sa account na ito?*");
                    }
                    return "Regarding Assessment **#{$a['assessment_no']} ({$a['taxpayer_name']})** from our conversation:\n\n"
                        . ($sur > 0 || $pen > 0
                            ? "It has a **₱" . number_format($sur, 2) . " (25% Surcharge)** and **₱" . number_format($pen, 2) . " (2% Monthly Interest)** because the due date (**{$a['due_date']}**) has passed.\n\n"
                              . "Under **Section 255 of the Local Government Code (RA 7160)**:\n"
                              . "- A **25% one-time surcharge** is applied to the unpaid Basic Tax (₱" . number_format($basic, 2) . ").\n"
                              . "- A **2% monthly interest** accrues for each month of delinquency.\n\n"
                              . "💬 *Would you like instructions on how to pay this online via GCash or Maya so no further monthly interest accrues?*"
                            : "Good news! Assessment **#{$a['assessment_no']}** currently has **₱0.00 surcharge and ₱0.00 penalty** because it is within the due date ({$a['due_date']}). Only the Basic Tax of **₱" . number_format($basic, 2) . "** applies.\n\n"
                              . "💬 *Would you like to know how to settle this online or check another assessment?*");
                }

                if (preg_match('/(paano|pano|panu|how|saan|where|bayad|pay|gcash|maya|online)/i', $question)) {
                    if ($isTagalog) {
                        return "Para po mabayaran ang **₱" . number_format($total, 2) . "** ng Assessment **#{$a['assessment_no']} ({$a['taxpayer_name']})**, may **2 paraan** po kayo:\n\n"
                            . "1. **Online via Citizen Portal (GCash / Maya / Card):**\n"
                            . "   - Mag-login sa Citizen Portal, piliin ang reference number na **`{$a['assessment_no']}`**, at bayaran ang **₱" . number_format($total, 2) . "**.\n"
                            . "   - Awtomatikong lalabas ang inyong **Electronic Official Receipt (`OR-2026-...`)**.\n"
                            . "2. **Walk-In sa Caloocan City Hall (City Treasurer's Office):**\n"
                            . "   - Ipakita lamang ang Assessment No. **`{$a['assessment_no']}`** o TD/Permit No. **`{$a['td_or_permit_no']}`** sa Revenue Collection Counter.\n\n"
                            . "💬 *Gusto po ba ninyong mag-check pa ng ibang assessment o may tanong pa kayo tungkol sa resibo?*";
                    }
                    return "To settle the **₱" . number_format($total, 2) . "** total due for Assessment **#{$a['assessment_no']} ({$a['taxpayer_name']})**, you have **2 convenient options**:\n\n"
                        . "1. **Pay Online (GCash, Maya, or Debit/Credit Card):**\n"
                        . "   - Open the Citizen Payment Portal, enter Assessment Reference **`{$a['assessment_no']}`**, and complete the PayMongo checkout for **₱" . number_format($total, 2) . "**.\n"
                        . "   - Your **Electronic Official Receipt (`OR-2026-...`)** will be issued immediately.\n"
                        . "2. **Over-the-Counter at Caloocan City Hall:**\n"
                        . "   - Present Assessment No. **`{$a['assessment_no']}`** or TD/Permit No. **`{$a['td_or_permit_no']}`** at the City Treasurer's Office Collection Window.\n\n"
                        . "💬 *Do you have any other questions about this payment, or would you like to inspect another account?*";
                }
            }

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
                        ? "✅ Ang account na ito ay **bayad na (Settled)**.\n\n💬 *May iba pa po ba kayong gustong i-check na assessment o itanong tungkol sa resibo nito?*"
                        : "💡 Maaari po kayong magbayad online gamit ang **GCash, Maya, o Card**.\n\n💬 *Pwede ninyo akong tanungin: \"Bakit may surcharge yan?\" o \"Paano ko babayaran yan online?\"*");
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
                    ? "✅ This assessment is already **PAID / Settled**.\n\n💬 *Would you like to check another assessment number or ask a follow-up question?*"
                    : "💡 You may settle this online via **GCash, Maya, or Debit/Credit Card**.\n\n💬 *You can ask me follow-up questions like: \"Why does this have a surcharge?\" or \"How do I pay this online?\"*");
        }

        // 2. If a specific Market Stall was matched (now or from history)
        if ($matched && $matched['type'] === 'market_stall') {
            $s    = $matched['data'];
            $rate = (float) ($s['monthly_rate'] ?? $s['monthly_fee'] ?? 0);
            $occ  = strtoupper((string) ($s['occupancy_status'] ?? $s['status'] ?? 'VACANT'));
            $lateSurcharge = round($rate * 0.25, 2);
            $lateTotal     = $rate + $lateSurcharge;

            if ($matchedFromHistory && preg_match('/(paano|pano|panu|how|apply|rent|upa|requirements|bayad|pay)/i', $question)) {
                if ($isTagalog) {
                    return "Tungkol po sa **Market Stall #{$s['stall_number']} ({$s['section']})** na pinag-uusapan natin:\n\n"
                        . ($occ === 'VACANT'
                            ? "Dahil **VACANT (Bakante)** po ang Stall **#{$s['stall_number']}** (₱" . number_format($rate, 2) . "/buwan), maaari po kayong mag-apply sa **Market Administration & City Treasurer's Office**:\n"
                              . "1. Magdala ng **Barangay Clearance**, **Cedula**, at **2x2 ID Picture**.\n"
                              . "2. Pumirma ng **Market Stall Lease Contract** at bayaran ang unang buwanang upa na **₱" . number_format($rate, 2) . "**.\n\n"
                              . "💬 *Gusto po ba ninyong malaman ang iba pang bakanteng stall sa palengke?*"
                            : "Ang Stall **#{$s['stall_number']}** ay kasalukuyang nakapangalan kay **{$s['vendor_name']}** (**{$occ}**). Maaaring bayaran ang buwanang upa na **₱" . number_format($rate, 2) . "** bago mag-ika-20 ng buwan sa Treasury Counter o Online.\n\n"
                              . "💬 *Gusto po ba ninyong makita kung aling mga stall ang bakante (vacant) ngayon?*");
                }
                return "Regarding **Market Stall #{$s['stall_number']} ({$s['section']})** from our chat:\n\n"
                    . ($occ === 'VACANT'
                        ? "Since Stall **#{$s['stall_number']}** is currently **VACANT** (₱" . number_format($rate, 2) . "/month), you can apply for it at the **Market Administration & City Treasurer's Office**:\n"
                          . "1. Submit a **Barangay Clearance**, **Community Tax Certificate (Cedula)**, and **Valid ID**.\n"
                          . "2. Sign the **Market Stall Lease Agreement** and pay the monthly rental of **₱" . number_format($rate, 2) . "**.\n\n"
                          . "💬 *Would you like to see what other stalls are currently vacant?*"
                        : "Stall **#{$s['stall_number']}** is currently assigned to **{$s['vendor_name']}** (**{$occ}**). The monthly rental of **₱" . number_format($rate, 2) . "** can be paid before the 20th of the month.\n\n"
                          . "💬 *Would you like me to list the vacant stalls available for lease?*");
            }

            if ($isTagalog) {
                return "Narito po ang live status ng **Market Stall #{$s['stall_number']} ({$s['section']})**:\n\n"
                    . "- **Nakatalagang Vendor:** " . ($s['vendor_name'] ?: 'Vacant / Available') . "\n"
                    . "- **Occupancy Status:** **{$occ}**\n"
                    . "- **Buwanang Upa (Monthly Rental):** **₱" . number_format($rate, 2) . "**\n"
                    . "- **Huling Petsa ng Bayad:** " . ($s['last_payment_date'] ?: 'Wala pang record ngayong buwan') . "\n"
                    . "- **Cutoff Date:** Tuwing **ika-20 ng buwan** (Kapag lumagpas, may **25% surcharge** na ₱" . number_format($lateSurcharge, 2) . " → Kabuuang **₱" . number_format($lateTotal, 2) . "**).\n\n"
                    . "💬 *May gusto po ba kayong itanong tungkol sa pagbabayad o pag-apply sa stall na ito?*";
            }

            return "Here is the live ledger status for **Market Stall #{$s['stall_number']} ({$s['section']})**:\n\n"
                . "- **Assigned Vendor:** " . ($s['vendor_name'] ?: 'Vacant / Available for Lease') . "\n"
                . "- **Occupancy Status:** **{$occ}**\n"
                . "- **Monthly Rental Rate:** **₱" . number_format($rate, 2) . "**\n"
                . "- **Last Payment Date:** " . ($s['last_payment_date'] ?: 'No recent payment recorded') . "\n"
                . "- **Payment Cutoff:** **20th of the month** (Late payments after the 20th incur a **25% surcharge** of ₱" . number_format($lateSurcharge, 2) . ", totaling **₱" . number_format($lateTotal, 2) . "**).\n\n"
                . "💬 *Feel free to ask a follow-up question about how to apply for or pay this stall!*";
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
                . "- **Payment Mode:** " . strtoupper((string) $c['payment_mode']) . " · **Date:** {$c['created_at']}\n\n"
                . "💬 *Do you need any other details about this Official Receipt?*";
        }

        // 4. Custom peso amount computation (even in follow-ups like "eh kung 25000?" or "paano pag 50000 ang puhunan/tax?")
        if (preg_match('/(\d[\d,]*(\.\d+)?)/', str_replace(',', '', $question), $numMatch)) {
            $val = (float) $numMatch[1];
            if ($val >= 100 && $val != 2026 && (
                preg_match('/(compute|surcharge|penalty|kwenta|kalkula|interes|late|eh kung|paano pag|pano pag|what if|how about)/i', $qLower)
                || ($hasHistory && preg_match('/(surcharge|penalty|komputasyon|computation)/i', $recentHistoryText))
            )) {
                $sur = round($val * 0.25, 2);
                $pen = round($val * 0.02, 2);
                $tot = $val + $sur + $pen;
                if ($isTagalog) {
                    return "### Komputasyon ng Penalty at Surcharge para sa ₱" . number_format($val, 2) . "\n\n"
                        . "- **Basic Tax / Principal:** ₱" . number_format($val, 2) . "\n"
                        . "- **25% Statutory Surcharge (One-Time Late Penalty):** ₱" . number_format($sur, 2) . "\n"
                        . "- **2% Monthly Interest (1 Buwan):** ₱" . number_format($pen, 2) . "\n"
                        . "- **Kabuuang Babayaran (Total Due):** **₱" . number_format($tot, 2) . "**\n\n"
                        . "*Paalala: Kung umabot ng 2 buwan ang delay, magiging 4% (₱" . number_format(round($val * 0.04, 2), 2) . ") ang interest.*\n\n"
                        . "💬 *May iba pa po ba kayong halaga na gustong ipag-compute o tanong tungkol dito?*";
                }
                return "### Statutory Tax Breakdown Computation for ₱" . number_format($val, 2) . "\n\n"
                    . "- **Basic Tax / Principal:** ₱" . number_format($val, 2) . "\n"
                    . "- **25% Statutory Surcharge (One-Time Late Penalty):** ₱" . number_format($sur, 2) . "\n"
                    . "- **2% Monthly Interest (1 Month):** ₱" . number_format($pen, 2) . "\n"
                    . "- **Total Amount Due:** **₱" . number_format($tot, 2) . "**\n\n"
                    . "*Note: If delayed for 2 months, the 2% monthly interest becomes 4% (₱" . number_format(round($val * 0.04, 2), 2) . ").*\n\n"
                    . "💬 *Would you like me to compute another amount or check a specific assessment?*";
            }
        }

        // 5. Intent-aware responses for New Business Registration vs Renewal vs Retirement (Check BEFORE generic follow-ups)
        if (preg_match('/(bago|bagong|new|register|rehistro|magparehistro|business name|pangalan ng negosyo|dti|sec|start|open|magtayo|apply)/i', $question) && preg_match('/(business|negosyo|permit|name|dti|mayor)/i', $question)) {
            if ($isTagalog) {
                return "### Paano Mag-register ng Bagong Business Name at Mayor's Permit sa Caloocan City\n\n"
                    . "Kung nais po ninyong magparehistro ng **bagong Business Name at Mayor's Permit**, narito po ang **4 na opisyal na hakbang**:\n\n"
                    . "- **Hakbang 1: Pagpaparehistro ng Business Name (Trade Name)**\n"
                    . "  - **Sole Proprietorship (Single Owner):** Mag-register muna ng Business Name sa **DTI** online sa `bnrs.dti.gov.ph`.\n"
                    . "  - **Corporation / Partnership:** Mag-register sa **SEC**.\n"
                    . "  - **Cooperative:** Mag-register sa **CDA**.\n"
                    . "- **Hakbang 2: Barangay Clearance at Locational Clearance**\n"
                    . "  - Kumuha ng **Barangay Business Clearance** sa barangay kung saan itatayo ang negosyo at **Locational / Zoning Clearance** sa City Hall.\n"
                    . "- **Hakbang 3: Pag-apply ng Bagong Mayor's Permit sa BPLO at Assessment sa Treasury**\n"
                    . "  - Ipasa ang DTI/SEC Certificate, Contract of Lease o Land Title, Barangay Clearance, at **Cedula** sa **Caloocan BPLO**.\n"
                    . "  - Kwentahin ng **City Treasurer's Office** ang inyong **Initial Business Tax** (base sa Capital Investment) at **Regulatory Fees**.\n"
                    . "- **Hakbang 4: Pagbabayad at Pagkuha ng Official Receipt (OR)**\n"
                    . "  - Magbayad sa **Treasury Collection Counter** o **Online (GCash / Maya)** upang makuha ang inyong **Official Receipt (OR)** at **Mayor's Permit**.\n\n"
                    . "💬 *Pwede ninyo akong tanungin: \"Saan ako pupunta para mapasa lahat ng ito?\", \"Magkano ang magagastos?\", o \"Gaano katagal ang proseso?\"*";
            }
            return "### How to Register a New Business Name & Mayor's Permit in Caloocan City\n\n"
                . "To register a **new business name and secure your Mayor's Permit**, follow these 4 official steps:\n\n"
                . "- **Step 1: Register Your Business Name (National Agency)**\n"
                . "  - **Sole Proprietorship:** Register your trade name with **DTI** (`bnrs.dti.gov.ph`).\n"
                . "  - **Corporation / Partnership:** Register with **SEC**.\n"
                . "  - **Cooperative:** Register with **CDA**.\n"
                . "- **Step 2: Secure Barangay & Locational Clearances**\n"
                . "  - Obtain a **Barangay Business Clearance** from the barangay where your business is located and a **Locational / Zoning Clearance** at Caloocan City Hall.\n"
                . "- **Step 3: BPLO Application & Treasury Tax Assessment**\n"
                . "  - Submit your DTI/SEC registration, lease contract or land title, Barangay Clearance, and **Community Tax Certificate (Cedula)** to the **Business Permits and Licensing Office (BPLO)**.\n"
                . "  - The **City Treasurer's Office** will issue a Tax Order of Payment based on your **Paid-Up Capital / Initial Investment** plus regulatory fees.\n"
                . "- **Step 4: Payment & Official Receipt (OR) Issuance**\n"
                . "  - Pay at the **Treasury Collection Counter** or **Online via GCash/Maya** to receive your Official Receipt and Mayor's Permit.\n\n"
                . "💬 *You can ask me follow-up questions like: \"Where do I go to submit all of these?\", \"How much are the registration fees?\", or \"How long does it take?\"*";
        }

        if (preg_match('/(close|retire|retirement|sara|isara|stop|tigil|kansela)/i', $question) && preg_match('/(business|negosyo|permit)/i', $question)) {
            if ($isTagalog) {
                return "### Pagpapasara ng Negosyo (Business Retirement / Closure) — Caloocan City\n\n"
                    . "Upang pormal na ipasara ang negosyo at **matigil ang tuloy-tuloy na patong ng quarterly tax at surcharge**:\n"
                    . "- **1.** Magpasa ng **Letter of Intent to Close** at **Barangay Certificate of Business Closure**.\n"
                    . "- **2.** Dalhin ang orihinal na **Mayor's Permit**, huling **Official Receipt (OR)**, at **Sworn Declaration of Gross Sales** hanggang sa araw ng pagsasara.\n"
                    . "- **3.** Kwentahin ng **City Treasurer's Office** ang anumang natitirang buwis bago mag-issue ng **Certificate of Business Retirement**.\n\n"
                    . "💬 *May natitira pa po bang unpaid quarter ang negosyong ipapasara ninyo para ma-compute natin?*";
            }
            return "### Business Retirement / Closure Procedure — Caloocan City\n\n"
                . "To formally close or retire a business in Caloocan City so quarterly taxes and surcharges stop accruing:\n"
                . "- **1.** Submit an **Application for Business Retirement** and **Barangay Certificate of Closure**.\n"
                . "- **2.** Surrender the original **Mayor's Permit**, latest **Official Receipt (OR)**, and **Financial Statement / Gross Sales Declaration** up to the date of closure.\n"
                . "- **3.** Settle any remaining tax due at the **City Treasurer's Office** to receive your **Certificate of Business Retirement**.\n\n"
                . "💬 *Do you have an existing assessment number you'd like me to check before retiring the business?*";
        }

        // 6. Universal Conversational Follow-Ups (Where to go/submit, How long, How much, Requirements, Business Type)
        if (preg_match('/(saan|san|where|location|address|opisina|office|city hall|pupunta|ipapasa|magpapasa|mag-aapply|pasa|mapasa)/i', $question)) {
            if ($isTagalog) {
                return "Narito po kung **saan kayo pupunta upang ipasa ang mga dokumento at magbayad sa Caloocan City**:\n\n"
                    . "1. **Unang Hakbang (Business Name - Online o DTI):**\n"
                    . "   - Maaari itong gawin online sa **`https://bnrs.dti.gov.ph`** o sa **DTI Negosyo Center** sa Caloocan City Hall.\n"
                    . "2. **Ikalawang Hakbang (Barangay Clearance):**\n"
                    . "   - Sa **Barangay Hall** na nakakasakop sa lugar ng inyong negosyo o ari-arian.\n"
                    . "3. **Ikatlong Hakbang (Pagpasa ng Lahat ng Requirements at Pagbabayad sa City Hall):**\n"
                    . "   - **South Caloocan City Hall (Main):** **Ground Floor, Business One-Stop Shop (BOSS) — BPLO at City Treasurer's Office**, 8th Street cor. 8th Avenue, Grace Park East, Caloocan City.\n"
                    . "   - **North Caloocan City Hall (Satellite):** **BPLO & Treasury Collection Windows**, Zapote Street, Camarin, Caloocan City.\n"
                    . "   - **Oras ng Opisina:** Lunes hanggang Biyernes, **8:00 AM – 5:00 PM** (Walang noon break).\n\n"
                    . "💬 *Gusto po ba ninyong malaman kung magkano ang babayaran o kung gaano katagal bago makuha ang permit?*";
            }
            return "Here is **where you need to go to submit your documents and process your transaction in Caloocan City**:\n\n"
                . "1. **Business Name Registration (Step 1):**\n"
                . "   - Online at **`https://bnrs.dti.gov.ph`** or at the **DTI Negosyo Center** inside Caloocan City Hall.\n"
                . "2. **Barangay Clearance (Step 2):**\n"
                . "   - At the **Barangay Hall** where your business or property is located.\n"
                . "3. **Submission of All Requirements & Tax Assessment (Step 3 & 4):**\n"
                . "   - **South Caloocan City Hall (Main):** **Ground Floor, Business One-Stop Shop (BOSS) — BPLO & City Treasurer's Office**, 8th Street cor. 8th Avenue, Grace Park East, Caloocan City.\n"
                . "   - **North Caloocan City Hall:** **Satellite BPLO & Treasury Collection Windows**, Zapote Street, Camarin, Caloocan City.\n"
                . "   - **Office Hours:** Monday to Friday, **8:00 AM – 5:00 PM**.\n\n"
                . "💬 *Would you like to know how much the registration fees are or how long the processing takes?*";
        }

        if (preg_match('/(gaano katagal|how long|ilang araw|days|processing time|mabilis ba|kailan makukuha|oras)/i', $question)) {
            if ($isTagalog) {
                return "Tungkol po sa **tagal ng proseso (processing time)**:\n\n"
                    . "- **DTI Business Name (Online sa `bnrs.dti.gov.ph`):** **15–30 minuto lamang** kapag nakabayad na via GCash/Maya, mada-download agad ang DTI Certificate.\n"
                    . "- **Barangay Business Clearance at Cedula:** **1 araw** (same-day release sa Barangay Hall o City Hall).\n"
                    . "- **Caloocan BPLO at City Treasurer's Office (BOSS One-Stop Shop):** **1 hanggang 3 working days** basta kumpleto na ang mga dokumentong ipinasa at nabayaran na ang Tax Order of Payment.\n\n"
                    . "💬 *Gusto po ba ninyong malaman kung magkano ang babayaran o ano ang mga dapat dalhin?*";
            }
            return "Here is the **estimated processing time**:\n\n"
                . "- **DTI Business Name (`bnrs.dti.gov.ph`):** **15–30 minutes** online (instant certificate download after GCash/Maya payment).\n"
                . "- **Barangay Business Clearance & Cedula:** **Same day (1 day)**.\n"
                . "- **Caloocan BPLO & Treasury Assessment (One-Stop Shop):** **1 to 3 working days** once complete documents are submitted and paid.\n\n"
                . "💬 *Would you like to know the estimated fees or documentary requirements?*";
        }

        if (preg_match('/(ano.*requirements|anong.*requirements|ano.*kailangan|anong.*kailangan|ano.*dadalhin|anong.*dadalhin|ano.*ipapasa|what.*requirements|what.*documents|kailangan dalhin)/i', $question)) {
            if ($isTagalog) {
                return "Narito po ang **kumpletong listahan ng mga dokumentong kailangan ninyong dalhin at ipasa**:\n\n"
                    . "1. **DTI Business Name Certificate** (kung Sole Proprietorship) o **SEC Registration** (kung Corporation/Partnership)\n"
                    . "2. **Barangay Business Clearance** (mula sa barangay kung saan naroon ang negosyo)\n"
                    . "3. **Katibayan ng Pwesto:**\n"
                    . "   - Kung umuupa: **Notarized Contract of Lease** at Mayor's Permit ng Lessor\n"
                    . "   - Kung sariling lupa/pwesto: **Transfer Certificate of Title (TCT)** o **Tax Declaration**\n"
                    . "4. **Community Tax Certificate (Cedula)** at **1 Valid Government ID**\n"
                    . "5. **Para sa Renewal:** Dalhin ang **Nakaraang Mayor's Permit**, **Official Receipt (OR)**, at **Sworn Declaration of Gross Sales / Financial Statement**.\n\n"
                    . "💬 *Kumpleto na po ba ang mga ito o may tanong kayo kung paano kumuha ng alinman dito?*";
            }
            return "Here is the **complete checklist of documentary requirements to bring and submit**:\n\n"
                . "1. **DTI Business Name Certificate** (Sole Proprietorship) or **SEC Registration** (Corporation/Partnership)\n"
                . "2. **Barangay Business Clearance** (from the barangay where the business operates)\n"
                . "3. **Proof of Business Location:**\n"
                . "   - If leased: **Notarized Contract of Lease**\n"
                . "   - If owned: **Land Title (TCT)** or **Real Property Tax Declaration**\n"
                . "4. **Community Tax Certificate (Cedula)** and **1 Valid Government ID**\n"
                . "5. **If Renewing:** Bring your **Previous Year's Mayor's Permit**, **Official Receipt (OR)**, and **Sworn Declaration of Gross Receipts**.\n\n"
                . "💬 *Do you already have these documents, or would you like to know where to get any of them?*";
        }

        if (preg_match('/(magkano|how much|cost|fee|gastos|babayaran|presyo|rate)/i', $question) && ($category === 'Business Tax & Permits' || $hasHistory)) {
            if ($isTagalog) {
                return "Tungkol po sa **gastos at babayaran sa pag-register ng Business Name at Mayor's Permit**:\n\n"
                    . "1. **DTI Business Name Registration Fee:**\n"
                    . "   - **Barangay Scope:** ₱200 + ₱30 Documentary Stamp Tax (DST)\n"
                    . "   - **City / Municipality Scope (Caloocan):** ₱500 + ₱30 DST\n"
                    . "   - **Regional Scope:** ₱1,000 + ₱30 DST | **National Scope:** ₱2,000 + ₱30 DST\n"
                    . "2. **Caloocan City Hall (Mayor's Permit & Treasury Fees):**\n"
                    . "   - **Initial Business Tax:** Karaniwang **1/20 ng 1% (0.05%) ng inyong Paid-Up Capital / Puhunan** para sa bagong negosyo (o base sa Gross Sales kapag renewal).\n"
                    . "   - **Regulatory Fees:** Mayor's Permit Fee, Sanitary Fee, Garbage Fee, Zoning Fee, at Fire Safety Inspection Fee (karaniwang nasa **₱1,500 hanggang ₱5,000+** depende sa laki at uri ng negosyo).\n\n"
                    . "💬 *Magkano po ba ang tinatayang puhunan (capital) o anong klaseng negosyo ang itatayo ninyo para mabigyan ko kayo ng mas eksaktong estimate?*";
            }
            return "Regarding the **costs and fees for Business Name & Mayor's Permit registration**:\n\n"
                . "1. **DTI Business Name Registration Fee:**\n"
                . "   - **Barangay Scope:** ₱200 + ₱30 Documentary Stamp Tax (DST)\n"
                . "   - **City / Municipality Scope (Caloocan):** ₱500 + ₱30 DST\n"
                . "   - **Regional Scope:** ₱1,000 + ₱30 DST | **National:** ₱2,000 + ₱30 DST\n"
                . "2. **Caloocan City Treasury & BPLO Fees:**\n"
                . "   - **Initial Business Tax:** Based on your **Initial Capital Investment** (or Gross Sales for renewals).\n"
                . "   - **Regulatory Fees:** Mayor's Permit, Sanitary, Zoning, Garbage, and Fire Safety Inspection Fee (typically **₱1,500 to ₱5,000+** depending on business size and category).\n\n"
                . "💬 *What type of business or initial capital are you planning so I can give you a closer estimate?*";
        }

        if (preg_match('/(sari-sari|karinderya|kainan|food|online shop|retail|tindahan|puhunan|capital)/i', $question)) {
            if ($isTagalog) {
                return "Magandang ideya po yan! Para sa **Sari-Sari Store / Tindahan / Food Business / Small Enterprise** sa Caloocan:\n\n"
                    . "- Kung ang puhunan ay maliit, piliin lamang ang **Barangay Scope (₱230)** o **City Scope (₱530)** sa DTI.\n"
                    . "- Kung **pagkain o tindahan** ang negosyo, kailangan din po ng **Health Certificate / Sanitary Permit** mula sa City Health Department bukod sa Barangay Clearance at Cedula.\n"
                    . "- Kung ang kabuuang asset ay hindi lalagpas sa ₱3 Milyon, maaari rin kayong mag-apply bilang **BMBE (Barangay Micro Business Enterprise)** para sa dagdag na benepisyo.\n\n"
                    . "💬 *May napili na po ba kayong pangalan ng negosyo o may tanong pa kayo sa Sanitary/Barangay requirements?*";
            }
            return "That's a great business choice! For a **Retail Store, Food Business, or Small Enterprise** in Caloocan City:\n\n"
                . "- For DTI registration, you only need **Barangay Scope (₱230)** or **City Scope (₱530)**.\n"
                . "- If you are handling food or beverages, you will also need a **Sanitary Permit & Health Card** from the Caloocan City Health Department alongside your Barangay Clearance and Cedula.\n\n"
                . "💬 *Do you have any other questions about getting your permits or tax assessment?*";
        }

        // 7. Category-based live data + ordinance response
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
                        . "3. **Live Assessment Lookup:** I-type lamang ang inyong Assessment No. (hal. {$sampleRefs}) upang makita ang eksaktong breakdown.\n\n"
                        . "💬 *Alin pong RPT Assessment o halaga ng amilyar ang gusto ninyong i-check natin?*";
                }
                return "### Real Property Tax (RPT) Guidelines & Computation — Caloocan City\n\n"
                    . "1. **Payment Deadlines:**\n"
                    . "   - **Annual Payment:** On or before **March 31**.\n"
                    . "   - **Quarterly Installments:** Mar 31 (Q1), Jun 30 (Q2), Sep 30 (Q3), Dec 31 (Q4).\n"
                    . "2. **Delinquency Penalties (RA 7160 Sec. 255):**\n"
                    . "   - **25% Surcharge** on the unpaid Basic Tax + Special Education Fund (SEF).\n"
                    . "   - **2% Monthly Interest** on the unpaid amount (plus surcharge) until fully paid (maximum of 36 months).\n"
                    . "3. **Check Your Live Assessment:** Enter your Assessment No. in the chat (e.g., {$sampleRefs}) for an instant itemized breakdown.\n\n"
                    . "💬 *Which RPT Assessment number or amount would you like me to compute for you?*";

            case 'Business Tax & Permits':
                if ($isTagalog) {
                    return "### Business Tax & Mayor's Permit Renewal — Caloocan City\n\n"
                        . "- **Deadline ng Renewal:** Tuwing **January 1 hanggang January 20** bawat taon (o quarterly: Jan 20, Apr 20, Jul 20, Oct 20).\n"
                        . "- **Mga Kailangang Dokumento sa Renewal:**\n"
                        . "  1. Nakaraang Mayor's Permit at Official Receipt\n"
                        . "  2. Sworn Declaration of Gross Sales / Receipts o Financial Statement\n"
                        . "  3. Barangay Business Clearance, Sanitary Permit, at Fire Safety Inspection Certificate (FSIC)\n"
                        . "  4. Community Tax Certificate (Cedula)\n"
                        . "- **Late Renewal Penalty:** **25% Surcharge** + **2% interes kada buwan**. Maaari ninyong i-type ang `BIZ-2026-001` o `TA-2026-102` upang tingnan ang live assessment.\n\n"
                        . "💬 *Magre-renew po ba kayo ng permit, magpapa-register ng bagong negosyo, o magpapa-compute ng business tax?*";
                }
                return "### Business Tax & Mayor's Permit Renewal — Caloocan City\n\n"
                    . "- **Renewal Deadline:** **January 20** annually (or quarterly on Jan 20, Apr 20, Jul 20, Oct 20).\n"
                    . "- **Documentary Requirements:**\n"
                    . "  1. Previous Year's Mayor's Permit & Official Receipt\n"
                    . "  2. Audited Financial Statements / Sworn Declaration of Gross Receipts\n"
                    . "  3. Barangay Clearance, Sanitary Permit, and Fire Safety Inspection Certificate (FSIC)\n"
                    . "  4. Community Tax Certificate (Cedula)\n"
                    . "- **Late Penalties:** **25% statutory surcharge** + **2% monthly interest**. Type an assessment number such as `BIZ-2026-001` or `TA-2026-102` to inspect a live account.\n\n"
                    . "💬 *Are you renewing an existing permit, registering a new business, or checking a specific assessment?*";

            case 'Market Stall Rental':
                $stalls = $ragContext['stalls'] ?? [];
                $vacant = array_filter($stalls, fn($s) => strtolower((string) ($s['occupancy_status'] ?? $s['status'] ?? '')) === 'vacant');
                $vacantList = implode(', ', array_map(fn($s) => "`{$s['stall_number']}` ({$s['section']} - ₱" . number_format((float) ($s['monthly_rate'] ?? $s['monthly_fee'] ?? 0), 2) . "/mo)", $vacant));
                if ($isTagalog) {
                    return "### Caloocan Public Market Stall Rental & Occupancy\n\n"
                        . "- **Buwanang Cutoff Date:** Tuwing **ika-20 ng buwan**.\n"
                        . "- **Late Payment Surcharge:** **25% surcharge** kapag lumagpas sa ika-20.\n"
                        . "- **Kabuuang Stalls sa Ledger:** **" . count($stalls) . " stalls** (" . (count($stalls) - count($vacant)) . " occupied, " . count($vacant) . " bakante).\n"
                        . "- **Mga Bakanteng Stall na Maaaring Apply-an:** " . ($vacantList ?: 'Wala sa ngayon') . "\n\n"
                        . "💬 *I-type lamang ang stall number gaya ng `A-01`, `A-03`, o `C-02` o tanungin kung paano mag-apply sa bakanteng pwesto!*";
                }
                return "### Caloocan Public Market Stall Rental & Occupancy\n\n"
                    . "- **Monthly Cutoff Date:** On or before the **20th of every month**.\n"
                    . "- **Late Payment Surcharge:** **25% surcharge** is automatically added after the 20th.\n"
                    . "- **Total Stalls in Ledger:** **" . count($stalls) . " stalls** (" . (count($stalls) - count($vacant)) . " occupied, " . count($vacant) . " vacant).\n"
                    . "- **Currently Vacant Stalls Available for Lease:** " . ($vacantList ?: 'None at the moment') . "\n\n"
                    . "💬 *Type any stall number like `A-01`, `A-03`, or `C-02`, or ask me how to apply for a vacant stall!*";

            case 'Cedula & Miscellaneous Fees':
                if ($isTagalog) {
                    return "### Community Tax Certificate (Cedula) — Caloocan City Treasury\n\n"
                        . "- **Basic Community Tax:** **₱5.00**.\n"
                        . "- **Additional Community Tax:** **₱1.00 para sa bawat ₱1,000.00** ng kabuuang kita (gross earnings) mula sa trabaho, propesyon, o negosyo noong nakaraang taon.\n"
                        . "- **Saan Kukuha:** Sa **Treasury Revenue Collection Window** sa Caloocan City Hall o Barangay Hall (magdala lamang ng 1 Valid Government ID).\n\n"
                        . "💬 *Gusto po ba ninyong ipa-compute kung magkano ang Cedula base sa inyong buwanan o taunang kita?*";
                }
                return "### Community Tax Certificate (Cedula) — Caloocan City Treasury\n\n"
                    . "- **Basic Community Tax:** **₱5.00**.\n"
                    . "- **Additional Tax:** **₱1.00 for every ₱1,000.00** of gross earnings/income from profession or business during the preceding year.\n"
                    . "- **Where to Secure:** At the **Treasury Revenue Collection Window** in Caloocan City Hall (present 1 valid Government ID).\n\n"
                    . "💬 *Would you like me to compute your exact Cedula amount based on your annual gross income?*";

            case 'Online Payment & Receipts':
                if ($isTagalog) {
                    return "### Online Tax & Fee Payment (PayMongo — GCash, Maya, Card)\n\n"
                        . "- **Mga Tinatanggap na Paraan ng Bayad:** **GCash, Maya, Credit/Debit Card, at Online Banking** sa pamamagitan ng Civentral Citizen Portal.\n"
                        . "- **Convenience Fee:** 1% gateway processing fee.\n"
                        . "- **Electronic Official Receipt (OR):** Kapag nakumpleto ang bayad, awtomatikong mag-i-issue ang sistema ng **Official Receipt (`OR-YYYY-...`)** at ire-record ito sa Treasury Fund Ledger.\n\n"
                        . "💬 *May Assessment No. o Official Receipt No. po ba kayo na gustong i-verify ngayon?*";
                }
                return "### Online Tax & Fee Payment (PayMongo — GCash, Maya, Card)\n\n"
                    . "1. **Supported Channels:** GCash, Maya, Credit/Debit Card, and Online Banking via the **Civentral Citizen Portal**.\n"
                    . "2. **Convenience Fee:** 1% gateway processing fee.\n"
                    . "3. **Automatic Official Receipt (OR):** Once paid, PayMongo notifies the Treasury webhook (`api/webhooks/paymongo.php`), automatically issues an Official Receipt (`OR-YYYY-...`), and records `+ Money In` in the corresponding Treasury Fund Ledger.\n\n"
                    . "💬 *Do you have an Assessment No. or Official Receipt No. you'd like me to verify right now?*";

            default:
                if ($isTagalog) {
                    return "Naiintindihan ko po ang inyong tanong: *\"" . htmlspecialchars($question, ENT_QUOTES, 'UTF-8') . "\"*.\n\n"
                        . "Bilang inyong **Caloocan City Treasury Conversational AI Assistant**, maaari tayong mag-usap tungkol sa:\n"
                        . "- 🏢 **Pagpaparehistro ng Bagong Negosyo o Renewal:** Hal. *\"Paano mag-register ng bagong business name?\"*, *\"Magkano ang bayad?\"*, *\"Gaano katagal?\"*\n"
                        . "- 🏠 **Real Property Tax (Amilyar) at Business Tax Lookup:** Hal. *\"Magkano ang RPT-2026-001?\"*, *\"Bakit may surcharge yan?\"*\n"
                        . "- 🏪 **Public Market Stall Rental:** Hal. *\"Ano ang status ng Stall A-03?\"*, *\"Paano mag-apply sa bakanteng stall?\"*\n"
                        . "- 🧮 **Komputasyon ng Surcharge at Penalty:** Hal. *\"Compute surcharge for 15000\"*\n\n"
                        . "💬 *Sabihin lang po ninyo kung alin dito ang gusto ninyong pag-usapan at sasagutin ko kayo!*";
                }
                return "I understand you're asking about: *\"" . htmlspecialchars($question, ENT_QUOTES, 'UTF-8') . "\"*.\n\n"
                    . "As your **Caloocan City Hall Treasury Conversational AI Assistant**, we can chat back and forth in **English or Tagalog** about:\n"
                    . "- 🏢 **New Business Registration & Renewal:** Try *\"How do I register a new business name?\"*, followed by *\"How much does it cost?\"* or *\"How long does it take?\"*\n"
                    . "- 🏠 **Live Tax Assessment Lookup:** Try *\"Check assessment RPT-2026-001\"*, then ask *\"Why does it have a surcharge?\"* or *\"How do I pay it?\"*\n"
                    . "- 🏪 **Market Stall Occupancy & Rates:** Try *\"Check market stall A-03\"* or *\"Which market stalls are vacant?\"*\n"
                    . "- 🧮 **Tax & Surcharge Computations:** Try *\"Compute surcharge and interest for 15000\"*\n\n"
                    . "💬 *Which of these can I help you with right now?*";
        }
    }

    public function detectCategory(string $question, array $history = []): string
    {
        $q = strtolower($question);
        if (preg_match('/(rpt|real property|amilyar|land tax|property tax|td-|arp)/i', $q)) {
            return 'Real Property Tax (RPT)';
        }
        if (preg_match('/(business|mayor|permit|gross|renewal|biz-|negosyo|dti|sec|sari-sari|tindahan|ta-2026|as-2026)/i', $q)) {
            return 'Business Tax & Permits';
        }
        if (preg_match('/(market|stall|vendor|pwesto|palengke|section|[a-d]-0\d|ms-[a-d])/i', $q)) {
            return 'Market Stall Rental';
        }
        if (preg_match('/(gcash|maya|online|paymongo|receipt|or-|checkout|card)/i', $q)) {
            return 'Online Payment & Receipts';
        }
        if (preg_match('/(cedula|sedula|community tax|ctc|clearance)/i', $q)) {
            return 'Cedula & Miscellaneous Fees';
        }

        // Multi-turn conversational inheritance: if current message is a follow-up question
        // (e.g. "magkano yan?", "gaano katagal?", "saan po ang opisina?", "bakit may penalty?"),
        // inherit the active category from recent conversation history!
        if (!empty($history)) {
            foreach (array_reverse(array_slice($history, -4)) as $turn) {
                $prevText = strtolower((string) ($turn['content'] ?? $turn['text'] ?? ''));
                if ($prevText === '') {
                    continue;
                }
                if (preg_match('/(rpt|real property|amilyar|land tax|property tax)/i', $prevText)) {
                    return 'Real Property Tax (RPT)';
                }
                if (preg_match('/(business|mayor|permit|negosyo|dti|bplo|biz-|ta-2026)/i', $prevText)) {
                    return 'Business Tax & Permits';
                }
                if (preg_match('/(market|stall|vendor|pwesto|palengke)/i', $prevText)) {
                    return 'Market Stall Rental';
                }
                if (preg_match('/(cedula|sedula|community tax)/i', $prevText)) {
                    return 'Cedula & Miscellaneous Fees';
                }
                if (preg_match('/(gcash|maya|paymongo|online)/i', $prevText)) {
                    return 'Online Payment & Receipts';
                }
            }
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

