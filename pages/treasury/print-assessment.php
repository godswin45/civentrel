<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_GET['id'])) {
    die('Invalid application ID.');
}

$appId = (int)$_GET['id'];
$app = $treasuryService->getBusinessApp($appId);

if (!$app) {
    die('Application not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order of Payment - <?= htmlspecialchars($app['application_no']) ?></title>
    <!-- Tailwind CSS (Include standard Tailwind for rendering the print view) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Print-specific styles to ensure it looks like a clean document */
        @media print {
            body { 
                background-color: white !important; 
                margin: 0;
                padding: 0;
            }
            .no-print { 
                display: none !important; 
            }
            .print-border {
                border: 1px solid #000 !important;
            }
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }
        .page-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 3rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        @media print {
            .page-container {
                box-shadow: none;
                margin: 0;
                padding: 1rem;
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    
    <div class="text-center mb-6 no-print">
        <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white font-bold rounded-lg shadow hover:bg-blue-700">
            Print Document
        </button>
        <button onclick="window.close()" class="px-6 py-2 bg-gray-200 text-gray-700 font-bold rounded-lg shadow hover:bg-gray-300 ml-2">
            Close Tab
        </button>
    </div>

    <div class="page-container border border-gray-300">
        <!-- Header -->
        <div class="flex items-center justify-between border-b-2 border-gray-800 pb-6 mb-6">
            <div class="flex items-center gap-4">
                <img src="../../assets/images/logo.png" alt="City Logo" class="h-20 w-20 object-contain grayscale" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-xl font-black uppercase tracking-wider text-gray-900"><?= htmlspecialchars(getenv('LGU_NAME') ?: 'Republic of the Philippines') ?></h1>
                    <p class="text-sm font-semibold uppercase text-gray-700">Office of the City Treasurer</p>
                    <p class="text-xs text-gray-500">Business Permits and Licensing Office</p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-black text-gray-900 uppercase">Order of Payment</h2>
                <p class="text-sm font-bold text-gray-600 mt-1">NO: <?= htmlspecialchars($app['application_no']) ?></p>
                <p class="text-xs text-gray-500">Date: <?= date('F j, Y') ?></p>
            </div>
        </div>

        <!-- Business Details -->
        <div class="mb-8">
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 border-b border-gray-200 pb-1">Taxpayer Details</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 font-semibold block text-xs">Business Name:</span>
                    <strong class="text-gray-900 text-base"><?= htmlspecialchars($app['business_name']) ?></strong>
                </div>
                <div>
                    <span class="text-gray-500 font-semibold block text-xs">Owner/Applicant:</span>
                    <strong class="text-gray-900"><?= htmlspecialchars($app['owner_name']) ?></strong>
                </div>
                <div class="col-span-2">
                    <span class="text-gray-500 font-semibold block text-xs">Business Address:</span>
                    <span class="text-gray-900"><?= htmlspecialchars($app['business_address'] ?? $app['barangay'] ?? 'N/A') ?></span>
                </div>
                <div>
                    <span class="text-gray-500 font-semibold block text-xs">Line of Business:</span>
                    <span class="text-gray-900"><?= htmlspecialchars($app['line_of_business'] ?? 'N/A') ?></span>
                </div>
                <div>
                    <span class="text-gray-500 font-semibold block text-xs">Transaction Type:</span>
                    <span class="text-gray-900 uppercase font-bold"><?= htmlspecialchars($app['transaction_type']) ?></span>
                </div>
            </div>
        </div>

        <!-- Assessment Details -->
        <div class="mb-8">
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 border-b border-gray-200 pb-1">Assessment Computation</h3>
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-100 border-y border-gray-300">
                        <th class="text-left py-2 px-3 font-bold text-gray-700">Description</th>
                        <th class="text-right py-2 px-3 font-bold text-gray-700">Amount (₱)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="py-3 px-3 text-gray-700">Business Tax (Assessed)</td>
                        <td class="py-3 px-3 text-right font-mono text-gray-900"><?= number_format($app['assessed_tax'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-3 px-3 text-gray-700">Regulatory Fees & Charges</td>
                        <td class="py-3 px-3 text-right font-mono text-gray-900"><?= number_format($app['regulatory_fees'] ?? 0, 2) ?></td>
                    </tr>
                    <!-- Total Row -->
                    <tr class="bg-gray-50">
                        <td class="py-4 px-3 text-right font-black uppercase text-gray-900">Total Amount Due</td>
                        <td class="py-4 px-3 text-right font-black text-lg border-t-2 border-gray-900">₱ <?= number_format($app['total_due'] ?? 0, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer / Instructions -->
        <div class="mt-12 text-sm text-gray-600 space-y-4">
            <p><strong>INSTRUCTIONS:</strong> Please present this Order of Payment to the City Treasurer's Office Cashier window to settle your assessment. You may also pay online via the Citizen Portal using GCash, Maya, or Credit/Debit Card by entering your Application Number.</p>
            
            <div class="flex justify-between items-end pt-12 border-t border-gray-200 mt-12">
                <div class="text-center">
                    <div class="border-b border-gray-400 w-48 mb-1"></div>
                    <p class="text-xs font-bold uppercase text-gray-700">Taxpayer Signature</p>
                </div>
                <div class="text-center">
                    <p class="font-bold text-gray-900 uppercase mb-1"><?= htmlspecialchars($app['assessed_by'] ?? 'City Assessor') ?></p>
                    <div class="border-t border-gray-400 w-48 pt-1">
                        <p class="text-xs font-bold uppercase text-gray-700">Assessing Officer</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Auto-print script with a slight delay to allow Tailwind to render -->
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
