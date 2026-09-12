# Citizen Treasury Payment System - Implementation Guide

## Quick Start

### 1. Apply Database Migrations
```bash
mysql -u your_user -p your_database < database/migrations/add_online_payments_fields.sql
```

### 2. Test the Payment Service
```bash
php tests/CitizenPaymentServiceTest.php
```

### 3. Postman Testing
- Import `tests/Citizen_Payment_API.postman_collection.json` into Postman
- Update `base_url` variable to your server URL
- Run requests

### 4. cURL Testing
```bash
# Make requests executable
chmod +x tests/api_test.sh

# Run test suite
./tests/api_test.sh
```

---

## Architecture Overview

```
civentral/
├── api/citizen/treasury/
│   ├── payments.php              # POST endpoint for payment processing
│   └── payments_get.php          # GET endpoints for history & details
│
├── src/
│   ├── Services/
│   │   └── CitizenPaymentService.php     # Core payment business logic
│   └── bootstrap.php             # Updated to include CitizenPaymentService
│
├── database/
│   └── migrations/
│       └── add_online_payments_fields.sql # Database schema
│
├── tests/
│   ├── CitizenPaymentServiceTest.php     # Unit tests
│   ├── api_test.sh                       # cURL test suite
│   └── Citizen_Payment_API.postman_collection.json
│
└── API_DOCUMENTATION.md          # Complete API reference
```

---

## Implementation Checklist

### ✅ Completed Components

#### 1. Database Schema
- [x] Enhanced `tr_online_payments` table with new fields
- [x] Created `tr_payment_gateway_log` table for audit trail
- [x] Added indexes for performance
- [x] Created migration script in `database/migrations/`

#### 2. Service Layer
- [x] `CitizenPaymentService` class with complete business logic
- [x] Payment validation (required fields, amounts, email, payment type)
- [x] Server-side amount recalculation (1% service fee)
- [x] Idempotency protection with `idempotency_key`
- [x] Database transaction support (rollback on failure)
- [x] Payment history retrieval
- [x] Single payment detail retrieval
- [x] Audit logging

#### 3. API Endpoints
- [x] `POST /api/citizen/treasury/payments` - Process payment
- [x] `GET /api/citizen/treasury/payments` - Get payment history
- [x] `GET /api/citizen/treasury/payments?transaction_id=...` - Get payment details
- [x] CORS support for mobile app
- [x] Comprehensive error responses

#### 4. Authentication & Authorization
- [x] Citizen session validation
- [x] Prevent unauthorized access
- [x] Citizens can only view their own payments
- [x] Session-based identification

#### 5. Testing
- [x] 8 unit test cases covering all scenarios
- [x] cURL test script with 11+ test cases
- [x] Postman collection for manual testing
- [x] Test cases include:
  - Successful payment
  - Validation failures
  - Idempotency testing
  - Unauthorized access
  - Amount recalculation
  - Payment history retrieval

#### 6. Documentation
- [x] API Documentation (API_DOCUMENTATION.md)
- [x] Implementation guide (this file)
- [x] Inline code comments
- [x] Test suite documentation

---

## API Endpoints Summary

### POST /api/citizen/treasury/payments
**Process online payment**
- Required fields: taxpayer_name, account_number, email, payment_type, amount, payment_method
- Returns: transaction_id, reference_no, receipt_no, status
- Idempotency: Uses idempotency_key to prevent duplicates
- Amount: Server recalculates service_fee and total_amount

### GET /api/citizen/treasury/payments
**Retrieve payment history**
- Query params: limit (default 50), offset (default 0)
- Returns: Array of payments for authenticated citizen
- Pagination: Supports offset-based pagination

### GET /api/citizen/treasury/payments?transaction_id=...
**Retrieve single payment details**
- Query param: transaction_id (required)
- Returns: Complete payment details including settled_at timestamp
- Authorization: Citizen can only view their own payments

---

## Supported Payment Types

1. Real Property Tax (→ PTF fund)
2. Business Tax & Fees (→ BSF fund)
3. Market Stall Rental (→ MSF fund)
4. Community Tax Certificate (→ GF fund)
5. General government payment / miscellaneous fees (→ GF fund)
6. Business permit renewal or retirement payment (→ BSF fund)

---

## Supported Payment Methods

- GCash
- Maya
- Bank Transfer
- Card

---

## Key Features in Detail

### 1. Server-Side Amount Calculation

**Problem**: Client could manipulate service fees and totals

**Solution**: All amounts recalculated on server
```php
$amount = (float) $input['amount'];
$serviceFee = round($amount * 0.01, 2);  // Always 1%, configurable
$totalAmount = $amount + $serviceFee;
// Client-provided values ALWAYS ignored
```

**Example**:
- Client sends: amount=4200, service_fee=999, total_amount=9999
- Server recalculates: service_fee=42, total_amount=4242
- Database stores: server-calculated values

### 2. Idempotency Protection

**Problem**: Network timeouts could cause duplicate payments

**Solution**: Unique idempotency_key prevents duplicates
```php
// First request creates payment with idempotency_key
// Second request with same key returns existing payment
// Result: Always returns original transaction_id
```

**Mobile Implementation**:
```javascript
const idempotencyKey = `${Date.now()}-${Math.random()}`;
const response1 = await fetch('/api/.../payments', {
  method: 'POST',
  body: JSON.stringify({...data, idempotency_key: idempotencyKey})
});
// Returns: {status: "success", transaction_id: "TXN-123"}

// Network timeout, mobile app retries with SAME idempotency_key
const response2 = await fetch('/api/.../payments', {
  method: 'POST',
  body: JSON.stringify({...data, idempotency_key: idempotencyKey})
});
// Returns: {status: "success", transaction_id: "TXN-123", isDuplicate: true}
// Same transaction! No double-charge!
```

### 3. Database Transactions

**Problem**: Partial success could leave inconsistent data

**Solution**: Atomic transactions with rollback
```php
try {
    $db->getPdo()->beginTransaction();
    
    1. Insert into tr_online_payments
    2. Call payment gateway
    3. Update payment status
    4. Insert into tr_collections
    5. Record gateway log
    
    $db->getPdo()->commit();  // All or nothing
} catch (Exception $e) {
    $db->getPdo()->rollBack();  // Everything reverted
}
```

### 4. Audit Trail

**Problem**: Need to track all payments and gateway interactions

**Solution**: Complete logging in tr_payment_gateway_log
```php
// Every request logged:
{
  payment_id: 123,
  gateway_name: "GCash",
  request_payload: {...},
  response_payload: {...},
  response_code: 200,
  status: "success",
  error_message: null,
  created_at: "2026-09-05 10:30:00"
}
```

---

## Configuration

### Change Service Fee Percentage
File: `src/Services/CitizenPaymentService.php`
```php
private const SERVICE_FEE_PERCENTAGE = 0.01;  // 1%, change to 0.02 for 2%
```

### Add More Supported Payment Types
File: `src/Services/CitizenPaymentService.php`
```php
private const SUPPORTED_PAYMENT_TYPES = [
    // Add new type here
    'New Payment Type'
];
```

### Configure CORS Origins
Files: `api/citizen/treasury/payments.php` and `payments_get.php`
```php
$allowedOrigins = [
    'http://localhost',
    'https://your-mobile-app-domain.com'
];
```

---

## Integration with civentral-apps

### Mobile App Flow

1. **User enters payment details**
   - Taxpayer name, account number, email
   - Payment type, amount, payment method
   - Mobile app generates unique idempotency_key

2. **Submit payment**
   ```
   POST /api/citizen/treasury/payments
   {
     "taxpayer_name": "...",
     "amount": 4200,
     "idempotency_key": "unique-key",
     ...
   }
   ```

3. **Receive response**
   ```
   {
     "status": "success",
     "transaction_id": "TXN-2026-ABCDEF",
     "receipt_no": "OR-2026-ABCDEF",
     "data": {
       "total_amount": 4242,
       "status": "Paid"
     }
   }
   ```

4. **Display receipt**
   - Show transaction ID
   - Show receipt number
   - Show total amount paid
   - Option to download/print receipt

5. **View payment history**
   ```
   GET /api/citizen/treasury/payments
   ```
   - Returns list of all payments by citizen
   - Shows status, amounts, dates
   - Can filter by date range

---

## Error Handling

### Validation Errors (400)
- Missing required fields
- Negative/zero amounts
- Invalid email format
- Invalid payment type

### Authentication Errors (401)
- Citizen not logged in
- Invalid session

### Authorization Errors (403)
- Trying to access another citizen's payment

### Not Found Errors (404)
- Transaction not found
- Payment doesn't belong to citizen

### Server Errors (500)
- Database connection failure
- Gateway communication failure
- Unexpected exceptions

---

## Performance Considerations

### Database Indexes
```sql
INDEX idx_online_pay_citizen (citizen_id)       -- Fast history lookup
INDEX idx_online_pay_idempotency (idempotency_key) -- Fast duplicate check
INDEX idx_online_pay_txn_id (transaction_id)    -- Fast detail lookup
INDEX idx_online_pay_status_date (status, created_at) -- Reporting
```

### Query Optimization
- Pagination (limit/offset) prevents loading all records
- Indexes used for all WHERE clauses
- SELECT only needed fields

### Gateway Timeout
- Configurable timeout for gateway calls
- Retry logic for failed payments
- Graceful degradation on timeout

---

## Testing Strategy

### Unit Tests (CitizenPaymentServiceTest.php)
- Tests business logic in isolation
- Mock database operations
- Verify calculations and validations
- Coverage: 8 test cases

### Integration Tests (api_test.sh)
- Tests actual endpoints
- Uses real database
- Verifies HTTP responses
- Coverage: 11+ test cases

### Manual Tests (Postman)
- Test through UI
- Verify payment flow
- Check error messages
- Test authentication

---

## Deployment Checklist

- [ ] Run database migration
- [ ] Update .env with any new config
- [ ] Test payment endpoint locally
- [ ] Run test suite: `php tests/CitizenPaymentServiceTest.php`
- [ ] Verify CORS settings for mobile app
- [ ] Test with actual payment gateway (if integrated)
- [ ] Set up monitoring/alerting
- [ ] Document transaction ID format
- [ ] Brief team on new endpoints
- [ ] Plan for gateway integration

---

## Future Enhancements

### Phase 2: Gateway Integration
- Integrate GCash API
- Integrate Maya API
- Handle gateway webhooks
- Process payment confirmations
- Handle payment reversals

### Phase 3: Advanced Features
- Installment plans
- Scheduled payments
- Payment receipts (PDF)
- Email confirmations
- SMS notifications

### Phase 4: Analytics
- Payment reports by payment type
- Revenue by citizen
- Payment method analytics
- Success/failure rates
- Peak usage times

---

## Support

### Common Issues

**Q: Idempotency key not preventing duplicates?**
A: Ensure mobile app uses same key format. Keys must be EXACT match (case-sensitive).

**Q: Service fee not calculating correctly?**
A: Check if SERVICE_FEE_PERCENTAGE is set correctly. Default is 0.01 (1%).

**Q: Can't access payment history?**
A: Verify $_SESSION['citizen_id'] is set. Citizens must be logged in.

**Q: Getting "Payment not found" for valid transaction?**
A: Check if citizen_id matches. Citizens can only view own payments.

---

## Files Modified/Created

### Created Files
- [x] `src/Services/CitizenPaymentService.php`
- [x] `api/citizen/treasury/payments.php`
- [x] `api/citizen/treasury/payments_get.php`
- [x] `database/migrations/add_online_payments_fields.sql`
- [x] `tests/CitizenPaymentServiceTest.php`
- [x] `tests/api_test.sh`
- [x] `tests/Citizen_Payment_API.postman_collection.json`
- [x] `API_DOCUMENTATION.md`
- [x] This file (IMPLEMENTATION.md)

### Modified Files
- [x] `src/bootstrap.php` - Added CitizenPaymentService initialization

---

## Questions?

Refer to:
1. `API_DOCUMENTATION.md` - Complete API reference
2. `src/Services/CitizenPaymentService.php` - Code comments
3. `tests/CitizenPaymentServiceTest.php` - Test examples
4. `API_DOCUMENTATION.md` - Troubleshooting section

---

**Status**: ✅ Complete and Ready for Testing

**Version**: 1.0.0

**Last Updated**: 2026-09-05
