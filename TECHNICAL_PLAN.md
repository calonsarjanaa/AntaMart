# AntaMart Transaction & Fulfillment MVP — Task Roadmap

This roadmap was established in **Phase 2 (Technical Planning)** and is tracked incrementally in accordance with `expected_agent_behaviour.md` (`AGENTS.md`).

---

## Task Summary (Total: 15 Tasks)

| Task ID | Task Description | Requirement IDs | Status | Commit |
|---|---|---|:---:|---|
| **T-01** | Domain Enums, Exceptions, & Base Adapter Interfaces | Foundation | ✅ Completed | `445c9b3` |
| **T-02** | Database Migrations (12 tables + test suite) | FR-TRX-003, FR-INV-001 | ✅ Completed | `58be8b2` |
| **T-03** | Eloquent Models & Model Relationships | — | ✅ Completed | `18caf7b` |
| **T-04** | Checkout Flow (`CheckoutService` + `CheckoutController` + `InventoryService` reserve) | FR-TRX-001 to 005, FR-INV-001 to 003 | ✅ Completed | `f45463a` |
| **T-05** | Xendit Adapter: Create Invoice Session | FR-PAY-001 | ✅ Completed | `77d46c3` |
| **T-06** | Xendit Webhook (`VerifyXenditWebhook` middleware + `XenditController` + `ProcessXenditCallback` job) | FR-PAY-003 to 006, FR-INV-004, FR-INV-005 | ✅ Completed | `f7eac69` |
| **T-07** | Payment Expiry (`ExpireUnpaidOrder` job + reservation release) | FR-PAY-002, FR-INV-006 | ✅ Implemented; verification pending | — |
| **T-08** | Cancellation Flow (`CancellationService` + Storefront & Admin Endpoints) | FR-CAN-001 to 005 | ✅ Implemented; verification pending | — |
| **T-09** | Manual Refund Tracking (`RefundService` + Admin Mark-Refunded Endpoint) | FR-REF-001 to 005, FR-INV-007 | ✅ Implemented; verification pending | — |
| **T-10** | Shipment Management (`ShipmentService` + Admin Tracking Endpoints) | FR-SHP-001, 003, 005 | ✅ Implemented; verification pending | — |
| **T-11** | Guest Order Tracking Endpoint + Rate Limiting | FR-HIS-001, FR-TRX-011 | ✅ Implemented; verification pending | — |
| **T-12** | Audit Trail (Events, `RecordAuditLog` Listener, `AuditService`) | TRX-012 | ✅ Implemented; verification pending | — |
| **T-13** | Comprehensive Integration & Feature Test Suite | All Requirements | ✅ Implemented | 80 tests, 493 assertions |
| **T-14** | API Documentation & Known Limitations Report | Documentation | ✅ Implemented | OpenAPI, Postman, setup & limits |
| **T-15** | Cash on Delivery checkout and cash collection | Payment, inventory, shipment | ✅ Implemented | — |

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

### ✅ T-03: Eloquent Models & Relationships
- Define Eloquent models in `app/Models/`:
  - `Order`, `OrderItem`, `OrderAddress`, `Payment`, `PaymentCallback`, `InventoryReservation`, `CancellationRequest`, `Refund`, `Shipment`, `AuditLog`, `ProductVariant`, `User`.
- Wire up relationships (`hasMany`, `belongsTo`, `hasOne`).
- Configure attribute casting (e.g. ULID, Enums, Decimals, JSON).
- Add helper scopes and methods.
- Write unit tests for models and relationships.

### ✅ T-04: Checkout Flow
- Implement `InventoryService::reserveStock()` with atomic database query (`stock_physical - stock_reserved >= requested`).
- Implement `CheckoutService::checkout()`:
  - Validates cart & active products.
  - Generates ULID for order.
  - Takes snapshots of product data, variants, and shipping address.
  - Deduplicates checkout using `idempotency_key`.
- Implement `CheckoutController` for both Auth and Guest users.

### ✅ T-05: Xendit Adapter (Invoice Creation)
- Implement concrete HTTP client call in `XenditAdapter::createInvoice()`.
- Configure 15-minute validity window on Xendit invoice.
- Link returned invoice ID and checkout URL to the order's payment record.

### ✅ T-06: Xendit Webhook Processing
- Implement `VerifyXenditWebhook` middleware using constant-time `hash_equals` on `x-callback-token`.
- Implement `XenditController` (returns `200 OK` immediately).
- Implement `ProcessXenditCallback` job:
  - Deduplicate callback using `payment_callbacks` table.
  - Atomic conversion: reservation converted to physical stock deduction.
  - Transition order to `processing`.
  - Handle late payments (flag for manual refund if order already expired).

### ✅ T-07: Payment Expiry Flow (Verification Pending)
- Dispatch `ExpireUnpaidOrder` on the database queue after the gateway invoice expiry is stored.
- If order is still `pending_payment`:
  - Transition order to `expired`.
  - Mark the payment `expired`.
  - Release reserved inventory atomically (`stock_reserved - quantity`).
  - Fire `PaymentExpired` event for audit logging.
- Payment and expiry transactions lock the order row so only one can consume or release its reservations.
- Run a Laravel database queue worker (`php artisan queue:work database`) for delayed expiry jobs to execute.

### ✅ T-08: Cancellation Flow (Verification Pending)
- Customer and guest cancellation request submission is available at `POST /api/orders/{id}/cancellation-requests`; guests must provide the exact guest email recorded on the order.
- Only one pending cancellation request is allowed per order.
- Customer and guest withdrawal is available at `POST /api/orders/{id}/cancellation-requests/{requestId}/withdraw` and is allowed only before Store Owner decision (`decided_at IS NULL`).
- Store Owner review is available at `POST /api/admin/orders/{id}/cancellation-requests/{requestId}/review`:
  - Approve: cancels a `processing` order and flags a paid order for `manual_refund_pending`.
  - Reject: requires a rejection reason.
- Store Owner immediate cancellation is available at `POST /api/admin/orders/{id}/cancel`, requires a reason, and only accepts `processing` orders.
- Cancellation does not restock paid items; restock remains gated by the manual refund flow (FR-INV-007).

### ✅ T-09: Manual Refund Tracking (Verification Pending)
- `RefundService` idempotently flags paid orders after cancellation or late payment.
- Store Owner endpoint: `POST /api/admin/orders/{id}/refund/mark-refunded`:
  - Records refund execution time, notes, and actor.
  - Updates refund status to `refunded`.
  - Accepts resellable order item IDs, persists the selection, and restocks only those items atomically.
- Restock is limited to item quantities with a corresponding converted reservation, so late payments on expired orders do not add stock that was never deducted.
- A refund can only be marked as `refunded` once; the status transition and any stock changes share a database transaction.

### ✅ T-10: Shipment Management (Verification Pending)
- Store Owner manual shipment creation: `POST /api/admin/orders/{id}/shipment`:
  - Requires a tracking number and accepts an optional tracking URL.
  - Transitions a `processing` order to `shipped` and records `shipped_at`.
- Store Owner delivery confirmation: `POST /api/admin/orders/{id}/shipment/confirm-delivery` records `delivered_at` and transitions the order to `completed`.
- Courier integration remains outside this task; the current shipment endpoint creates manual shipments.

### ✅ T-11: Guest Order Tracking (Verification Pending)
- Public endpoint: `GET /api/track?order_id=...&email=...`
- Requires exact match of Order ID and Guest Email; authenticated customer orders are not returned by this endpoint.
- Returns order items, payment and order state, shipment tracking and delivery timestamps, and refund status without exposing address or contact details.
- Throttled per IP (`throttle:10,1`) to prevent brute-force lookups.

### ✅ T-12: Audit Trail (Verification Pending)
- `AuditEvent` and `PaymentExpired` are recorded by the synchronous `RecordAuditLog` listener through `AuditService`.
- Successful critical operations record actor, order, event name, and minimal event metadata in `audit_logs`:
  - `order.created`, `payment.session_created`, `payment.confirmed`, `payment.expired`, `payment.late_detected`, `cancellation.requested`, `cancellation.approved`, `cancellation.rejected`, `cancellation.withdrawn`, `refund.flagged`, `refund.marked_refunded`, `stock.deducted`, `tracking.added`, `shipment.delivered`.
- Audit inserts run inside the corresponding database transaction where the operation is transactional.

### ✅ T-13: Comprehensive Test Suite
- Full Pest feature tests cover checkout/idempotency, payment session creation, callback handling and expiry, cancellation requests and decisions, refund/restock behavior, shipment/delivery workflows, guest order lookup, audit events, and migration structure.
- Verified with `php artisan test`: 70 tests passed (355 assertions).
- The roadmap refers to 16 identified edge cases, but no enumerated edge-case list is present in the repository. The suite covers documented requirements and implementation-derived edge cases; an exact 16-case mapping remains pending that source list.

### ✅ T-14: Documentation & Final Verification
- Replaced the Laravel starter README with AntaMart setup, environment, queue worker, API reference, and test instructions.
- Added an OpenAPI 3.0 contract for the current API routes and a Postman collection with configurable local, guest, store-owner, and webhook variables.
- Added a Known Limitations report covering payment, callback, queue, manual refund/shipping, guest access, security, and verification notes.
- Final verification: run `php artisan test` and validate the API artifacts before commit.

### ✅ T-15: Cash on Delivery

- Checkout accepts `xendit` or `cod`; omitted API payment methods continue to default to Xendit.
- COD orders enter `processing` immediately with payment pending, no payment expiry, and committed inventory.
- The Store Owner confirms cash collection while confirming delivery; payment changes to `paid` only after that confirmation.
- Cancelling an unpaid COD order before shipment restores its committed inventory and does not create a refund.
- Browser and API checkout, delivery confirmation, tracking, owner order details, OpenAPI, and Postman documentation cover the COD lifecycle.

## Follow-on: UI Implementation

The storefront and store-owner UI are tracked separately in [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md), starting with the Laravel web foundation and minimum catalog read path.

