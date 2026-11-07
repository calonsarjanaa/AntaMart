# AntaMart Transaction & Fulfillment MVP — Task Roadmap

This roadmap was established in **Phase 2 (Technical Planning)** and is tracked incrementally in accordance with `expected_agent_behaviour.md` (`AGENTS.md`).

---

## Task Summary (Total: 14 Tasks)

| Task ID | Task Description | Requirement IDs | Status | Commit |
|---|---|---|:---:|---|
| **T-01** | Domain Enums, Exceptions, & Base Adapter Interfaces | Foundation | ✅ Completed | `445c9b3` |
| **T-02** | Database Migrations (12 tables + test suite) | FR-TRX-003, FR-INV-001 | ✅ Completed | `58be8b2` |
| **T-03** | Eloquent Models & Model Relationships | — | ✅ Completed | `18caf7b` |
| **T-04** | Checkout Flow (`CheckoutService` + `CheckoutController` + `InventoryService` reserve) | FR-TRX-001 to 005, FR-INV-001 to 003 | ✅ Completed | `f94c560` |
| **T-05** | Xendit Adapter: Create Invoice Session | FR-PAY-001 | ⏳ **Next** | — |
| **T-06** | Xendit Webhook (`VerifyXenditWebhook` middleware + `XenditController` + `ProcessXenditCallback` job) | FR-PAY-003 to 006, FR-INV-004, FR-INV-005 | 📋 Pending | — |
| **T-07** | Payment Expiry (`ExpireUnpaidOrder` job + reservation release) | FR-PAY-002, FR-INV-006 | 📋 Pending | — |
| **T-08** | Cancellation Flow (`CancellationService` + Storefront & Admin Endpoints) | FR-CAN-001 to 005 | 📋 Pending | — |
| **T-09** | Manual Refund Tracking (`RefundService` + Admin Mark-Refunded Endpoint) | FR-REF-001 to 005, FR-INV-007 | 📋 Pending | — |
| **T-10** | Shipment Management (`ShipmentService` + Admin Tracking Endpoints) | FR-SHP-001, 003, 005 | 📋 Pending | — |
| **T-11** | Guest Order Tracking Endpoint + Rate Limiting | FR-HIS-001, FR-TRX-011 | 📋 Pending | — |
| **T-12** | Audit Trail (Events, `RecordAuditLog` Listener, `AuditService`) | TRX-012 | 📋 Pending | — |
| **T-13** | Comprehensive Integration & Feature Test Suite | All Requirements | 📋 Pending | — |
| **T-14** | API Documentation & Known Limitations Report | Documentation | 📋 Pending | — |

---

## Detailed Task Breakdown

### ✅ T-01: Domain Enums, Exceptions, and Base Interfaces
- **OrderStatus** (`pending_payment`, `processing`, `cancelled`, `shipped`, `completed`, `expired`) with strict state machine validation.
- **PaymentStatus** (`pending`, `paid`, `expired`, `failed`).
- **CancellationStatus** (`pending`, `approved`, `rejected`, `withdrawn`) with `isDecided()` rule.
- **RefundStatus** (`not_requested`, `manual_refund_pending`, `refunded`).
- Custom Exceptions: `InsufficientStockException`, `DuplicateCheckoutException`, `InvalidPaymentCallbackException`, `InvalidOrderStateException`.
- Contracts: `PaymentGatewayInterface`, `ShippingProviderInterface`, with initial stubs for Xendit and Manual Shipping.

### ✅ T-02: Database Migrations
- Added `role` to `users` table (`customer`, `store_owner`).
- Created tables:
  1. `orders` (with ULID `CHAR(26)`, `idempotency_key` unique, snapshot fields, timestamps).
  2. `order_items` (`product_snapshot`, `variant_snapshot`, prices, quantities).
  3. `order_addresses` (immutable address snapshot).
  4. `payments` (`xendit_invoice_id`, `late_payment_detected_at`, status).
  5. `payment_callbacks` (webhook deduplication on `xendit_payment_id`).
  6. `inventory_reservations` (for atomic reserve & release).
  7. `cancellation_requests` (separate table for customer cancellation review).
  8. `refunds` (manual refund tracking table).
  9. `shipments` (manual tracking numbers and status).
  10. `audit_logs` (append-only audit trail).
  11. `product_variants` (physical & reserved stock columns).

### ⏳ T-03: Eloquent Models & Relationships (Current Task)
- Define Eloquent models in `app/Models/`:
  - `Order`, `OrderItem`, `OrderAddress`, `Payment`, `PaymentCallback`, `InventoryReservation`, `CancellationRequest`, `Refund`, `Shipment`, `AuditLog`, `ProductVariant`, `User`.
- Wire up relationships (`hasMany`, `belongsTo`, `hasOne`).
- Configure attribute casting (e.g. ULID, Enums, Decimals, JSON).
- Add helper scopes and methods.
- Write unit tests for models and relationships.

### 📋 T-04: Checkout Flow
- Implement `InventoryService::reserveStock()` with atomic database query (`stock_physical - stock_reserved >= requested`).
- Implement `CheckoutService::checkout()`:
  - Validates cart & active products.
  - Generates ULID for order.
  - Takes snapshots of product data, variants, and shipping address.
  - Deduplicates checkout using `idempotency_key`.
- Implement `CheckoutController` for both Auth and Guest users.

### 📋 T-05: Xendit Adapter (Invoice Creation)
- Implement concrete HTTP client call in `XenditAdapter::createInvoice()`.
- Configure 15-minute validity window on Xendit invoice.
- Link returned invoice ID and checkout URL to the order's payment record.

### 📋 T-06: Xendit Webhook Processing
- Implement `VerifyXenditWebhook` middleware using constant-time `hash_equals` on `x-callback-token`.
- Implement `XenditController` (returns `200 OK` immediately).
- Implement `ProcessXenditCallback` job:
  - Deduplicate callback using `payment_callbacks` table.
  - Atomic conversion: reservation converted to physical stock deduction.
  - Transition order to `processing`.
  - Handle late payments (flag for manual refund if order already expired).

### 📋 T-07: Payment Expiry Flow
- Implement `ExpireUnpaidOrder` queued job (delayed by 15 minutes).
- If order is still `pending_payment`:
  - Transition order to `expired`.
  - Release reserved inventory atomically (`stock_reserved - quantity`).
  - Fire `PaymentExpired` event for audit logging.

### 📋 T-08: Cancellation Flow
- Customer submission: only on `processing` orders, creates `cancellation_requests` record.
- Customer withdrawal: allowed only before Store Owner decision (`decided_at IS NULL`).
- Store Owner review:
  - Approve: cancels order, transitions refund state to `manual_refund_pending`.
  - Reject: requires rejection reason.
- Store Owner immediate cancellation on `processing` order.

### 📋 T-09: Manual Refund Tracking
- `RefundService` flags orders after paid cancellation or late payment.
- Store Owner endpoint: `POST /api/admin/orders/{id}/refund/mark-refunded`:
  - Records refund execution time, notes, and actor.
  - Updates refund status to `refunded`.
  - Conditionally restocks resellable items.

### 📋 T-10: Shipment Management
- Store Owner shipment creation: `POST /api/admin/orders/{id}/shipment`:
  - Sets manual tracking number.
  - Transitions order to `shipped`.
- Store Owner delivery confirmation: transitions order to `completed`.

### 📋 T-11: Guest Order Tracking
- Public endpoint: `GET /api/track?order_id=...&email=...`
- Requires exact match of Order ID and Guest Email.
- Throttled per IP (`throttle:10,1`) to prevent brute-force lookups.

### 📋 T-12: Audit Trail
- Event listeners for all critical operations:
  - `order.created`, `payment.session_created`, `payment.confirmed`, `payment.expired`, `payment.late_detected`, `cancellation.requested`, `cancellation.approved`, `cancellation.rejected`, `cancellation.withdrawn`, `refund.flagged`, `refund.marked_refunded`, `stock.deducted`, `tracking.added`.
- Recorded into `audit_logs` table.

### 📋 T-13: Comprehensive Test Suite
- Full Pest feature tests covering all positive paths, negative paths, and the 16 identified edge cases.

### 📋 T-14: Documentation & Final Verification
- API documentation, Postman collection / OpenAPI spec, and Known Limitations report.

