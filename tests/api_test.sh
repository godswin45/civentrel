#!/bin/bash

# ============================================================
# Citizen Treasury Payment API - Test Script
# 
# This script contains example cURL commands to test all
# payment endpoints and various scenarios
# ============================================================

# Base URL - Update as needed
BASE_URL="http://localhost/civentral"
SESSION_ID="your_session_id_here"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Citizen Payment API Test Suite${NC}"
echo -e "${YELLOW}========================================${NC}\n"

# ============================================================
# TEST 1: Successful Payment Processing
# ============================================================
echo -e "${YELLOW}TEST 1: Successful Payment Processing${NC}"
echo "---"

IDEMPOTENCY_KEY="test-$(date +%s)-$(openssl rand -hex 4)"

curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Maria Santos\",
    \"account_number\": \"T-20485\",
    \"email\": \"maria.santos@example.com\",
    \"payment_type\": \"Business Tax & Fees\",
    \"amount\": 4200,
    \"service_fee\": 42,
    \"total_amount\": 4242,
    \"payment_method\": \"GCash\",
    \"notes\": \"Business tax payment\",
    \"source\": \"civentral-apps\",
    \"municipality_code\": \"CAL-2026\",
    \"idempotency_key\": \"$IDEMPOTENCY_KEY\",
    \"citizen_user_id\": 123
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 2: Missing Required Field (account_number)
# ============================================================
echo -e "${YELLOW}TEST 2: Validation Error - Missing Field${NC}"
echo "---"

curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"John Doe\",
    \"email\": \"john@example.com\",
    \"payment_type\": \"Business Tax & Fees\",
    \"amount\": 5000,
    \"payment_method\": \"GCash\",
    \"idempotency_key\": \"test-$(date +%s)-missing-field\"
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 3: Invalid Amount (Negative)
# ============================================================
echo -e "${YELLOW}TEST 3: Validation Error - Negative Amount${NC}"
echo "---"

curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Jane Doe\",
    \"account_number\": \"T-12345\",
    \"email\": \"jane@example.com\",
    \"payment_type\": \"Real Property Tax\",
    \"amount\": -1000,
    \"payment_method\": \"Maya\",
    \"idempotency_key\": \"test-$(date +%s)-negative-amount\"
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 4: Invalid Email Format
# ============================================================
echo -e "${YELLOW}TEST 4: Validation Error - Invalid Email${NC}"
echo "---"

curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Test User\",
    \"account_number\": \"T-99999\",
    \"email\": \"invalid-email\",
    \"payment_type\": \"Market Stall Rental\",
    \"amount\": 2000,
    \"payment_method\": \"GCash\",
    \"idempotency_key\": \"test-$(date +%s)-invalid-email\"
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 5: Invalid Payment Type
# ============================================================
echo -e "${YELLOW}TEST 5: Validation Error - Invalid Payment Type${NC}"
echo "---"

curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Test User\",
    \"account_number\": \"T-99999\",
    \"email\": \"test@example.com\",
    \"payment_type\": \"Invalid Payment Type\",
    \"amount\": 2000,
    \"payment_method\": \"GCash\",
    \"idempotency_key\": \"test-$(date +%s)-invalid-type\"
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 6: Duplicate Submission (Idempotency)
# ============================================================
echo -e "${YELLOW}TEST 6: Idempotency Test - Duplicate Submission${NC}"
echo "---"

IDEMPOTENCY_KEY_DUPLICATE="test-duplicate-$(date +%s)"

echo "First Submission:"
FIRST_RESPONSE=$(curl -s -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Alice Smith\",
    \"account_number\": \"T-IDEM-001\",
    \"email\": \"alice@example.com\",
    \"payment_type\": \"Community Tax Certificate\",
    \"amount\": 500,
    \"payment_method\": \"Maya\",
    \"notes\": \"First submission\",
    \"source\": \"civentral-apps\",
    \"municipality_code\": \"CAL-2026\",
    \"idempotency_key\": \"$IDEMPOTENCY_KEY_DUPLICATE\",
    \"citizen_user_id\": 123
  }")

echo "$FIRST_RESPONSE" | jq .

echo -e "\nSecond Submission (Same Idempotency Key):"
curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Alice Smith\",
    \"account_number\": \"T-IDEM-001\",
    \"email\": \"alice@example.com\",
    \"payment_type\": \"Community Tax Certificate\",
    \"amount\": 500,
    \"payment_method\": \"Maya\",
    \"notes\": \"Second submission (duplicate)\",
    \"source\": \"civentral-apps\",
    \"municipality_code\": \"CAL-2026\",
    \"idempotency_key\": \"$IDEMPOTENCY_KEY_DUPLICATE\",
    \"citizen_user_id\": 123
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 7: Server-Side Amount Recalculation
# ============================================================
echo -e "${YELLOW}TEST 7: Server-Side Amount Recalculation${NC}"
echo "---"
echo "Note: Sending incorrect service_fee (999) and total_amount (9999)"
echo "Server should recalculate to: service_fee=42, total_amount=4242\n"

curl -X POST "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "{
    \"taxpayer_name\": \"Bob Johnson\",
    \"account_number\": \"T-CALC-001\",
    \"email\": \"bob@example.com\",
    \"payment_type\": \"Business Tax & Fees\",
    \"amount\": 4200,
    \"service_fee\": 999,
    \"total_amount\": 9999,
    \"payment_method\": \"GCash\",
    \"notes\": \"Testing amount recalculation\",
    \"source\": \"civentral-apps\",
    \"municipality_code\": \"CAL-2026\",
    \"idempotency_key\": \"test-calc-$(date +%s)\",
    \"citizen_user_id\": 123
  }" | jq .

echo -e "\n"

# ============================================================
# TEST 8: Get Payment History
# ============================================================
echo -e "${YELLOW}TEST 8: Get Payment History${NC}"
echo "---"

curl -X GET "$BASE_URL/api/citizen/treasury/payments?limit=10&offset=0" \
  -H "Cookie: PHPSESSID=$SESSION_ID" | jq .

echo -e "\n"

# ============================================================
# TEST 9: Get Single Payment Details
# ============================================================
echo -e "${YELLOW}TEST 9: Get Single Payment Details${NC}"
echo "---"
echo "Note: Replace TRANSACTION_ID with actual transaction ID from previous tests\n"

# You'll need to replace TXN-XXXX-XXXX with an actual transaction ID
curl -X GET "$BASE_URL/api/citizen/treasury/payments?transaction_id=TXN-2026-ABCDEF" \
  -H "Cookie: PHPSESSID=$SESSION_ID" | jq .

echo -e "\n"

# ============================================================
# TEST 10: Get Payment History with Pagination
# ============================================================
echo -e "${YELLOW}TEST 10: Get Payment History with Pagination${NC}"
echo "---"

curl -X GET "$BASE_URL/api/citizen/treasury/payments?limit=5&offset=5" \
  -H "Cookie: PHPSESSID=$SESSION_ID" | jq .

echo -e "\n"

# ============================================================
# TEST 11: Unauthorized Access (No Session)
# ============================================================
echo -e "${YELLOW}TEST 11: Unauthorized Access - No Session${NC}"
echo "---"

curl -X GET "$BASE_URL/api/citizen/treasury/payments" \
  -H "Content-Type: application/json" | jq .

echo -e "\n"

# ============================================================
# TEST SUMMARY
# ============================================================
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Test Suite Complete${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Summary of tests:"
echo "1. ✓ Successful Payment Processing"
echo "2. ✓ Missing Required Field Validation"
echo "3. ✓ Invalid Amount Validation"
echo "4. ✓ Invalid Email Validation"
echo "5. ✓ Invalid Payment Type Validation"
echo "6. ✓ Duplicate Submission (Idempotency)"
echo "7. ✓ Server-Side Amount Recalculation"
echo "8. ✓ Get Payment History"
echo "9. ✓ Get Single Payment Details"
echo "10. ✓ Get Payment History with Pagination"
echo "11. ✓ Unauthorized Access"
echo ""
echo "Notes:"
echo "- Update BASE_URL if testing on different server"
echo "- Update SESSION_ID with actual PHP session ID"
echo "- Replace transaction IDs with actual IDs from your tests"
echo "- Install jq for pretty JSON output: sudo apt-get install jq"
