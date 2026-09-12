# Citizen Treasury Payment API Documentation

## Overview
Complete backend payment processing system for civentral-apps mobile application with database transactions, idempotency protection, and comprehensive error handling.

---

## Endpoints

### 1. POST /api/citizen/treasury/payments
**Process online payment from citizen/mobile app**

#### Request Headers
```
Content-Type: application/json
```

#### Request Body
```json
{
  "taxpayer_name": "Maria Santos",
  "account_number": "T-20485",
  "email": "maria.santos@example.com",
  "payment_type": "Business Tax & Fees",
  "amount": 4200,
  "payment_method": "GCash",
  "notes": "Payment purpose (optional)",
  "source": "civentral-apps",
  "municipality_code": "CAL-2026",
  "idempotency_key": "unique-client-generated-key",
  "citizen_user_id": 123
}
```

#### Supported Payment Types
- Real Property Tax
- Business Tax & Fees
- Market Stall Rental
- Community Tax Certificate
- General government payment / miscellaneous fees
- Business permit renewal or retirement payment

#### Supported Payment Methods
- GCash
- Maya
- Bank Transfer
- Card

#### Response (Success - 200)
```json
{
  "status": "success",
  "message": "Payment accepted by the online payment gateway.",
  "transaction_id": "TXN-2026-ABCDEF",
  "reference_no": "REF-20260905-ABCDEF",
  "receipt_no": "OR-2026-ABCDEF",
  "data": {
    "transaction_id": "TXN-2026-ABCDEF",
    "reference_no": "REF-20260905-ABCDEF",
    "receipt_no": "OR-2026-ABCDEF",
    "status": "Paid",
    "amount": 4200.00,
    "service_fee": 42.00,
    "total_amount": 4242.00
  }
}
```

#### Response (Duplicate Submission - 200)
When the same `idempotency_key` is submitted twice, the service returns the original payment result:

```json
{
  "status": "success",
  "message": "This payment was already processed. Here are the details.",
  "transaction_id": "TXN-2026-ABCDEF",
  "reference_no": "REF-20260905-ABCDEF",
  "receipt_no": "OR-2026-ABCDEF",
  "data": {
    "transaction_id": "TXN-2026-ABCDEF",
    "reference_no": "REF-20260905-ABCDEF",
    "receipt_no": "OR-2026-ABCDEF",
    "status": "Paid",
    "amount": 4200.00,
    "service_fee": 42.00,
    "total_amount": 4242.00
  },
  "isDuplicate": true
}
```

#### Response (Error - 400/422)
```json
{
  "status": "error",
  "message": "Missing required field: account_number"
}
```

#### Example cURL Request
```bash
curl -X POST http://localhost/civentral/api/citizen/treasury/payments \
  -H "Content-Type: application/json" \
  -d '{
    "taxpayer_name": "Maria Santos",
    "account_number": "T-20485",
    "email": "maria.santos@example.com",
    "payment_type": "Business Tax & Fees",
    "amount": 4200,
    "payment_method": "GCash",
    "notes": "Business tax payment",
    "source": "civentral-apps",
    "municipality_code": "CAL-2026",
    "idempotency_key": "mobile-' + Date.now() + '-' + Math.random(),
    "citizen_user_id": 123
  }'
```

---

### 2. GET /api/citizen/treasury/payments
**Retrieve payment history for authenticated citizen**

#### Authentication
Requires `citizen_id` in PHP session (`$_SESSION['citizen_id']`)

#### Query Parameters
- `limit` (optional): Maximum number of records (default: 50, max: 100)
- `offset` (optional): Pagination offset (default: 0)
- `transaction_id` (optional): If provided, returns details for single transaction instead of history

#### Response (Payment History - 200)
```json
{
  "status": "success",
  "message": "Payment history retrieved",
  "count": 5,
  "data": [
    {
      "transaction_id": "TXN-2026-ABCDEF",
      "reference_no": "REF-20260905-ABCDEF",
      "receipt_no": "OR-2026-ABCDEF",
      "status": "Paid",
      "amount": 4200.00,
      "service_fee": 42.00,
      "total_amount": 4242.00,
      "payment_type": "Business Tax & Fees",
      "payment_method": "GCash",
      "created_at": "2026-09-05T10:30:00"
    }
  ]
}
```

#### Response (Single Transaction - 200)
```json
{
  "status": "success",
  "message": "Payment details retrieved",
  "data": {
    "transaction_id": "TXN-2026-ABCDEF",
    "reference_no": "REF-20260905-ABCDEF",
    "receipt_no": "OR-2026-ABCDEF",
    "status": "Paid",
    "amount": 4200.00,
    "service_fee": 42.00,
    "total_amount": 4242.00,
    "payment_type": "Business Tax & Fees",
    "payment_method": "GCash",
    "taxpayer_name": "Maria Santos",
    "account_number": "T-20485",
    "email": "maria.santos@example.com",
    "created_at": "2026-09-05T10:30:00",
    "settled_at": "2026-09-05T10:35:00"
  }
}
```

#### Response (Unauthorized - 401)
```json
{
  "status": "error",
  "message": "Unauthorized: Please log in as a citizen to access payment history."
}
```

#### Response (Not Found - 404)
```json
{
  "status": "error",
  "message": "Payment not found or unauthorized access."
}
```

#### Example cURL Request - History
```bash
curl -X GET "http://localhost/civentral/api/citizen/treasury/payments?limit=10&offset=0" \
  -H "Cookie: PHPSESSID=your_session_id"
```

#### Example cURL Request - Single Transaction
```bash
curl -X GET "http://localhost/civentral/api/citizen/treasury/payments?transaction_id=TXN-2026-ABCDEF" \
  -H "Cookie: PHPSESSID=your_session_id"
```

---

## Error Handling

### Error Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | Success | Payment processed or history retrieved |
| 400 | Validation Error | Missing or invalid fields |
| 401 | Unauthorized | Citizen not logged in |
| 404 | Not Found | Transaction not found |
| 405 | Method Not Allowed | Wrong HTTP method |
| 422 | Unprocessable Entity | Business logic validation failed |
| 500 | Internal Server Error | Database or gateway error |

### Common Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| `Missing required field: {field}` | Required field not provided | Include all required fields |
| `Amount must be greater than zero` | Amount <= 0 | Ensure amount is positive |
| `Invalid email format` | Email doesn't match RFC5321 | Use valid email address |
| `Invalid payment_type: {type}` | Payment type not in supported list | Use supported payment type |
| `Unauthorized: Citizen authentication required` | No citizen session or citizen_user_id provided | Log in as citizen first |
| `Payment not found or unauthorized access` | Transaction not found or doesn't belong to citizen | Verify transaction_id |

---

## Key Features

### 1. **Server-Side Amount Calculation**
Service fees are ALWAYS recalculated on the server. Client-provided `service_fee` and `total_amount` are IGNORED.

```
Calculation:
service_fee = amount × 1% (configurable)
total_amount = amount + service_fee
```

Example:
- Request amount: ₱4,200
- Client claims service_fee: ₱999 (ignored)
- Server calculates: ₱42 (1% of 4200)
- Final total: ₱4,242

### 2. **Idempotency Protection**
The `idempotency_key` prevents duplicate payments from being processed.

```
First Request:  {idempotency_key: "abc123"} → Creates payment, returns transaction_id: "TXN-123"
Second Request: {idempotency_key: "abc123"} → Returns same payment, isDuplicate: true
```

**Mobile Implementation Best Practice:**
```javascript
const idempotencyKey = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
const response = await fetch('/api/citizen/treasury/payments', {
  method: 'POST',
  body: JSON.stringify({...paymentData, idempotency_key: idempotencyKey})
});
```

### 3. **Database Transaction Support**
All payment operations are wrapped in database transactions:
- Record created in `tr_online_payments`
- Collection record created in `tr_collections`
- Gateway logs recorded in `tr_payment_gateway_log`
- If ANY step fails, ENTIRE transaction is rolled back

### 4. **Audit Trail**
Every payment request is logged:
- Request payload
- Gateway response
- Response code
- Status (success/failure)
- Error messages
- Timestamp and payment_id

---

## Database Schema

### tr_online_payments
```sql
CREATE TABLE tr_online_payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  payment_reference VARCHAR(100) UNIQUE,
  transaction_id VARCHAR(100) UNIQUE,
  reference_no VARCHAR(100) UNIQUE,
  receipt_no VARCHAR(100) UNIQUE,
  idempotency_key VARCHAR(255) UNIQUE,
  citizen_id INT,
  citizen_name VARCHAR(255),
  taxpayer_name VARCHAR(255),
  account_number VARCHAR(100),
  email VARCHAR(255),
  payment_type VARCHAR(150),
  payment_source VARCHAR(150),
  amount DECIMAL(14,2),
  service_fee DECIMAL(10,2),
  total_amount DECIMAL(14,2),
  fund_id VARCHAR(50),
  fund_code VARCHAR(50),
  payment_gateway ENUM('gcash','maya','card','bank'),
  payment_method VARCHAR(50),
  gateway_reference VARCHAR(100),
  notes TEXT,
  source VARCHAR(100),
  municipality_code VARCHAR(50),
  status ENUM('processing','completed','failed','cancelled'),
  gateway_response JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  settled_at TIMESTAMP,
  INDEX (citizen_id),
  INDEX (transaction_id),
  INDEX (idempotency_key),
  INDEX (status)
);
```

### tr_collections
```sql
-- Enhanced with:
-- - or_number (linked to payment receipt_no)
-- - payment_mode (includes online payment methods)
-- - collected_by (shows "Online Gateway (civentral-apps)")
```

### tr_payment_gateway_log
```sql
CREATE TABLE tr_payment_gateway_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  payment_id INT,
  gateway_name VARCHAR(50),
  request_payload JSON,
  response_payload JSON,
  response_code INT,
  status VARCHAR(50),
  error_message TEXT,
  created_at TIMESTAMP,
  FOREIGN KEY (payment_id) REFERENCES tr_online_payments(id)
);
```

---

## Integration with civentral-apps

The mobile app (treasury-service.ts) calls this endpoint:

```typescript
async function submitPayment(paymentData: PaymentRequest): Promise<PaymentResponse> {
  const response = await fetch('/api/citizen/treasury/payments', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      taxpayer_name: paymentData.taxpayer_name,
      account_number: paymentData.account_number,
      email: paymentData.email,
      payment_type: paymentData.payment_type,
      amount: paymentData.amount,
      payment_method: paymentData.payment_method,
      notes: paymentData.notes,
      source: 'civentral-apps',
      municipality_code: 'CAL-2026',
      idempotency_key: generateIdempotencyKey(),
      citizen_user_id: currentUserID
    })
  });
  
  return response.json();
}
```

---

## Testing

### Run Unit Tests
```bash
cd /path/to/civentral
php tests/CitizenPaymentServiceTest.php
```

### Test Cases
1. ✓ Successful payment processing
2. ✓ Validation failure (missing fields)
3. ✓ Validation failure (invalid amount)
4. ✓ Validation failure (invalid email)
5. ✓ Validation failure (invalid payment type)
6. ✓ Duplicate submission (idempotency)
7. ✓ Unauthorized access (no session)
8. ✓ Server-side amount recalculation

---

## Configuration

### Service Fee Percentage
Currently set to 1% (`CitizenPaymentService::SERVICE_FEE_PERCENTAGE = 0.01`)

To change, modify in `src/Services/CitizenPaymentService.php`:
```php
private const SERVICE_FEE_PERCENTAGE = 0.02;  // 2%
```

### CORS Allowed Origins
Configure in `/api/citizen/treasury/payments.php` and `payments_get.php`:
```php
$allowedOrigins = [
    'http://localhost',
    'http://localhost:3000',
    'https://civentral-apps.example.com'
];
```

### Payment Gateway
Currently simulates successful gateway response. To integrate real gateway:

1. Update `CitizenPaymentService::callPaymentGateway()` method
2. Call actual gateway API (GCash, Maya, etc.)
3. Handle gateway responses and errors
4. Store gateway reference for reconciliation

---

## Security Considerations

### 1. Authentication
- Citizens must be logged in (`$_SESSION['citizen_id']` required)
- Can only view their own payment history
- Cannot access other citizens' payments

### 2. Amount Validation
- Server ALWAYS recalculates amounts
- Client-provided values are NEVER used
- Prevents fraud attempts to modify fees/totals

### 3. Idempotency Protection
- Each payment must have unique `idempotency_key`
- Prevents accidental duplicate submissions
- Mobile app responsible for generating unique keys

### 4. Database Transactions
- All changes wrapped in transaction
- Rollback on ANY failure
- Ensures data consistency

### 5. Audit Logging
- All gateway calls logged
- Complete request/response stored
- Enables dispute resolution

---

## Development Roadmap

### Phase 1: MVP (Current)
- ✅ Payment processing API
- ✅ Idempotency protection
- ✅ Database schema
- ✅ Server-side validation
- ✅ Test suite

### Phase 2: Gateway Integration
- ⏳ Actual GCash integration
- ⏳ Maya payment gateway
- ⏳ Bank transfer support
- ⏳ Card payment processing

### Phase 3: Reconciliation
- ⏳ Batch settlement reports
- ⏳ Payment reconciliation
- ⏳ Refund processing
- ⏳ Failed payment recovery

### Phase 4: Advanced
- ⏳ Installment payments
- ⏳ Scheduled payments
- ⏳ Payment plans
- ⏳ Multi-currency support

---

## Support & Troubleshooting

### Issue: "Unauthorized: Citizen authentication required"
**Cause**: Citizen not logged in
**Solution**: Ensure citizen completes login before calling endpoint

### Issue: "Missing required field"
**Cause**: Required field not in request
**Solution**: Check request payload against documentation

### Issue: "Invalid payment_type"
**Cause**: Payment type not in supported list
**Solution**: Use one of: Real Property Tax, Business Tax & Fees, Market Stall Rental, Community Tax Certificate, General government payment / miscellaneous fees, Business permit renewal or retirement payment

### Issue: "Payment processing failed"
**Cause**: Database error or gateway error
**Solution**: Check server logs, verify database connectivity

---

## License & Credits
CIVENTRAL Treasury Payment System
Part of CIVENTRAL Municipal Management Platform
