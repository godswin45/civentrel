<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// Allow access if returning from PayMongo with a reference (no session needed for citizens)
if (empty($_GET['reference']) && empty($_SESSION['user_id']) && empty($_SESSION['employee_id']) && empty($_SESSION['citizen_id'])) {
    header('Location: ../../login.php');
    exit;
}

$errorMsg = null;
$successMsg = null;
$payment = null;

// Handle payment callback
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    $status = $_GET['status'] ?? 'failed';

    try {
        $gatewayResponse = [
            'status'         => $status,
            'reference'      => 'PAY-' . time(),
            'transaction_id' => 'TXN-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 16)),
            'timestamp'      => date('c'),
        ];

        $payment = $treasuryService->processOnlinePayment($reference, $gatewayResponse);

        if (($payment['status'] ?? '') === 'completed') {
            $successMsg = true;
        } else {
            $errorMsg = 'Payment failed. Please try again or contact support.';
        }
    } catch (Exception $e) {
        if ($e->getMessage() === 'Payment already processed.') {
            // Fetch the payment to show the receipt
            $results = $db->query("SELECT * FROM tr_online_payments WHERE payment_reference = ? LIMIT 1", [$reference]);
            $payment  = $results[0] ?? null;
            if ($payment) {
                $successMsg = true;
            } else {
                $errorMsg = 'Payment already processed but could not retrieve details.';
            }
        } else {
            $errorMsg = $e->getMessage();
        }
    }
}

$orNumber  = $payment['or_number'] ?? null;
$amount    = $payment['amount'] ?? null;
$payRef    = $payment['payment_reference'] ?? ($_GET['reference'] ?? '');
$createdAt = $payment['created_at'] ?? null;
$gateway   = strtoupper($payment['payment_gateway'] ?? $payment['payment_method'] ?? 'Online');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Result — Civentral</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f4c75 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 32px 64px rgba(0,0,0,0.3);
        }

        /* ── Success state ── */
        .icon-circle {
            width: 80px; height: 80px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            font-size: 2rem;
        }
        .icon-circle.success { background: #dcfce7; color: #16a34a; }
        .icon-circle.error   { background: #fee2e2; color: #dc2626; }

        .logo {
            display: flex; align-items: center; justify-content: center;
            gap: 8px; margin-bottom: 32px;
        }
        .logo img { height: 32px; }
        .logo span { font-weight: 800; font-size: 1.1rem; color: #0f172a; letter-spacing: -0.5px; }

        h1.title { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; }
        h1.title.success { color: #15803d; }
        h1.title.error   { color: #b91c1c; }

        .subtitle { font-size: 0.9rem; color: #64748b; margin-bottom: 32px; line-height: 1.5; }

        /* ── Receipt rows ── */
        .receipt {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: left;
            margin-bottom: 32px;
        }
        .receipt-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.82rem;
        }
        .receipt-row:last-child { border-bottom: none; }
        .receipt-row .label { color: #94a3b8; font-weight: 500; }
        .receipt-row .value { font-weight: 700; color: #1e293b; text-align: right; max-width: 60%; word-break: break-all; }
        .receipt-row .value.or { color: #0f4c75; font-size: 0.9rem; }
        .receipt-row .value.amount { color: #15803d; font-size: 1rem; }

        .or-pending {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fef9c3; color: #854d0e;
            border-radius: 8px; padding: 4px 10px;
            font-size: 0.78rem; font-weight: 600;
        }

        /* ── Buttons ── */
        .btn-primary {
            display: block; width: 100%;
            background: linear-gradient(135deg, #0f4c75, #1e3a5f);
            color: #fff; font-weight: 700; font-size: 0.9rem;
            border: none; border-radius: 12px;
            padding: 14px; cursor: pointer;
            text-decoration: none; margin-bottom: 12px;
            transition: opacity 0.2s;
        }
        .btn-primary:hover { opacity: 0.9; }

        .btn-secondary {
            display: block; width: 100%;
            background: transparent;
            color: #0f4c75; font-weight: 600; font-size: 0.9rem;
            border: 2px solid #0f4c75; border-radius: 12px;
            padding: 13px; cursor: pointer;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-secondary:hover { background: #eff6ff; }

        .info-box {
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 12px; padding: 14px 16px;
            font-size: 0.8rem; color: #166534;
            margin-bottom: 24px; text-align: left;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .info-box i { margin-top: 2px; flex-shrink: 0; }
    </style>
</head>
<body>
<div class="card">

    <!-- Logo -->
    <div class="logo">
        <img src="../../assets/images/logo.png" alt="Civentral" onerror="this.style.display='none'" />
        <span>CIVENTRAL</span>
    </div>

    <?php if ($successMsg): ?>

        <!-- SUCCESS -->
        <div class="icon-circle success">
            <i class="fa-solid fa-check"></i>
        </div>

        <h1 class="title success">Payment Successful!</h1>
        <p class="subtitle">Your payment has been received and is being processed.<br>Please keep your reference number for your records.</p>

        <div class="receipt">
            <div class="receipt-row">
                <span class="label">Reference No.</span>
                <span class="value"><?= htmlspecialchars($payRef ?: 'N/A') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">OR Number</span>
                <span class="value or">
                    <?php if ($orNumber): ?>
                        <?= htmlspecialchars($orNumber) ?>
                    <?php else: ?>
                        <span class="or-pending"><i class="fa-solid fa-clock"></i> Pending Issuance</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($amount): ?>
            <div class="receipt-row">
                <span class="label">Amount Paid</span>
                <span class="value amount">₱<?= number_format((float)$amount, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="label">Gateway</span>
                <span class="value"><?= htmlspecialchars($gateway) ?></span>
            </div>
            <?php if ($createdAt): ?>
            <div class="receipt-row">
                <span class="label">Date &amp; Time</span>
                <span class="value"><?= date('M j, Y g:i A', strtotime($createdAt)) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$orNumber): ?>
        <div class="info-box">
            <i class="fa-solid fa-circle-info"></i>
            <span>Your OR Number will be issued after the revenue office confirms your payment. You may check your payment history in the Civentral app.</span>
        </div>
        <?php endif; ?>

        <a href="javascript:void(0)" onclick="window.close()" class="btn-primary">
            <i class="fa-solid fa-circle-check"></i> &nbsp; Done — Close This Tab
        </a>

    <?php elseif ($errorMsg): ?>

        <!-- ERROR -->
        <div class="icon-circle error">
            <i class="fa-solid fa-xmark"></i>
        </div>

        <h1 class="title error">Payment Failed</h1>
        <p class="subtitle"><?= htmlspecialchars($errorMsg) ?></p>

        <a href="javascript:void(0)" onclick="window.close()" class="btn-primary" style="background: linear-gradient(135deg,#dc2626,#b91c1c);">
            Close This Tab
        </a>

    <?php else: ?>

        <!-- FALLBACK -->
        <div class="icon-circle" style="background:#f1f5f9;color:#64748b">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <h1 class="title" style="color:#334155">Processing…</h1>
        <p class="subtitle">We are verifying your payment. Please wait a moment.</p>

    <?php endif; ?>

</div>
</body>
</html>