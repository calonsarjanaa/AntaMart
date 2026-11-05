# Product Requirements Document (PRD)

# Transaction & Fulfillment — AntaMart (MVP Edition)

| Information | Detail | 
| ----- | ----- | 
| Product | AntaMart E-Commerce | 
| Document | Product Requirements Document | 
| Module | Transaction & Fulfillment | 
| Version | 1.1 (MVP Edition) | 
| Status | Draft — Ready for Review | 
| Product Type | Single-Store E-Commerce Platform | 
| Primary Users | Store Owner, Customer (Authenticated & Guest) | 

## 1. Overview

### 1.1 Background

AntaMart E-Commerce is a single-store e-commerce platform designed as a reusable starter kit for various businesses selling physical products.
The Transaction & Fulfillment module is responsible for managing the entire transaction process, from order creation, payment, stock management, cancellation, manual refunds, to product delivery to the Customer.
This module must ensure transactions are processed consistently, securely, and without discrepancies between order, payment, inventory, and shipment data.

### 1.2 Objectives

The objectives of developing this module are:

1. Allow Customers (both Registered and Guests) to checkout and create orders efficiently.

2. Integrate payments via a payment gateway.

3. Prevent overselling through stock reservation mechanisms.

4. Provide cancellation mechanisms for Customers and Store Owners.

5. **Manage refunds manually outside the system, with status tracking inside the system.**

6. Support manual delivery and courier integration.

7. Maintain transaction data consistency via validation, idempotency, and audit trails.

### 1.3 Success Criteria

The module is considered successful if:

* Customers (including Guests) can create orders and complete payments.

* Unpaid orders automatically expire based on the time limit.

* The system prevents purchases exceeding available stock.

* Each payment is processed effectively only once.

* Cancellations follow defined authorizations and state transitions.

* Delivery address and shipping costs remain consistent on the order.

* Store Owners can manage the order fulfillment process.

* Customers can view their order and delivery status.

## 2. Scope

### 2.1 In Scope

| ID | Feature | Description | 
| ----- | ----- | ----- | 
| TRX-001 | Checkout (Guest & Auth) | Order creation process by Authenticated Customers and Guests. | 
| TRX-002 | Order Management | Order data and status management. | 
| TRX-003 | Payment Integration | Payment session creation and callback reception. | 
| TRX-004 | Payment Expiry | Management of the 15-minute payment time limit. | 
| TRX-005 | Stock Reservation | Stock allocation when an order is created. | 
| TRX-006 | Inventory Deduction | Physical stock deduction after successful payment. | 
| TRX-007 | Cancellation | Order cancellation submission and management. | 
| TRX-008 | Manual Refund Tracking | Tracking of refunds executed manually by the Store Owner outside the system. | 
| TRX-009 | Shipping Management | Management of shipping methods and shipment status. | 
| TRX-010 | Address Snapshot | Saving the shipping address at the time the order is created. | 
| TRX-011 | Order Tracking | Order history for Auth Customers and Order Tracking for Guests. | 
| TRX-012 | Audit Trail | Recording critical transaction-related activities. | 

### 2.2 Out of Scope

The following features are excluded from the MVP scope:

* Multi-vendor or marketplace functionalities.

* Multi-warehouse inventory.

* International shipping.

* End-to-end item return management.

* Reshipment of completed orders.

* Cash on Delivery (COD).

* Manual bank transfer payments verified by the Store Owner.

* **Automated refunds via Payment Gateway integration.**

* Complex payment dispute management.

## 3. User Roles

### 3.1 Customer (Authenticated & Guest)

Customers are users who purchase products through the storefront.
Access rights:

* View products and pricing.

* Manage the shopping cart.

* Perform checkout (as a logged-in user or guest).

* Create orders.

* Make payments via the payment gateway.

* View order history (Auth) or track order status via Order ID & Email (Guest).

* Submit cancellation requests based on conditions.

* Withdraw cancellation requests before Store Owner decision.

* View delivery and manual refund status.

### 3.2 Store Owner

The Store Owner is the owner and manager of the shop.
Access rights:

* View all orders, transaction, and payment details.

* Process orders and shipments.

* Initiate order cancellations with provided reasons.

* Approve or reject Customer cancellation requests.

* **Execute refunds manually outside the system (e.g., bank transfer) and update the refund status to "Refunded" in the system.**

* Update manual shipping statuses and add tracking numbers.

* View transaction activity history.

## 4. Functional Requirements

### 4.1 Checkout & Order Creation

**FR-TRX-001 — Guest & Authenticated Checkout**
The system must allow both Authenticated Customers and Guests to checkout. Guests must provide a valid email and phone number for communication and order tracking.

**FR-TRX-002 — Cart Validation**
Before order creation, the system must re-validate:

* Product is active and available.

* Product price is valid.

* Product variants are available.

* Available stock is sufficient.

* Shipping address is complete.

* Shipping method is available for the destination.

**FR-TRX-003 — Order Creation**
The system must create an order based on validated checkout data.
Minimum order data includes: Order ID, Customer ID (or null for Guest), Guest Email/Phone, Product list, Variants, SKU, Quantity, Price, Subtotal, Shipping cost, Total payment, Shipping Address, Shipping Method, Created At, Order Status.

**FR-TRX-004 — Order Snapshot**
Product info, price, variants, address, and shipping cost must be saved as a snapshot so master data changes do not alter historical transactions.

**FR-TRX-005 — Duplicate Checkout Prevention**
The system must prevent duplicate orders caused by double-clicks, retries, or network instability.

### 4.2 Payment Management

**FR-PAY-001 — Payment Session Creation**
After an order is successfully created, the system must create a payment session through the payment gateway.

**FR-PAY-002 — Payment Expiry**
The payment validity period is 15 minutes since the payment session was successfully created by the gateway and successfully recorded by the system.

**FR-PAY-003 — Payment Status**
The system must manage the payment status based on validated information from the payment gateway or backend reconciliation results.

**FR-PAY-004 — Payment Validation**
The system must validate:

* The signature or callback authentication mechanism.

* Payment reference.

* Order reference.

* Payment amount.

* Transaction status.

* Gateway/provider identity.

**FR-PAY-005 — Idempotent Payment Processing**
Callbacks for payments received multiple times must not result in duplicate payment records or multiple stock deductions.

**FR-PAY-006 — Late Payment Reconciliation**
If a payment is received after the payment has expired, the system must reconcile.
If the gateway confirms funds are received:

1. The order remains in the `expired` state.

2. The system logs the late payment.

3. **The system flags the transaction for a "Manual Refund".**

4. The system does not automatically reactivate the expired order.

### 4.3 Inventory & Stock Reservation

**FR-INV-001 — Stock Reservation**
When an order is successfully created, the system must perform a reservation for the ordered stock quantity.

**FR-INV-002 — Available Stock Calculation**
Available stock is calculated using the formula:
`Available Stock = Physical Stock - Reserved Stock`

**FR-INV-003 — Stock Availability Validation**
The system may only accept a reservation if the available stock is sufficient.

**FR-INV-004 — Physical Stock Deduction**
Physical stock is deducted after the payment is successfully confirmed by the system.

**FR-INV-005 — Reservation Conversion**
The reservation must be converted into a physical stock deduction atomically.

**FR-INV-006 — Reservation Release**
A reservation is released if:

* The payment expires.

* The order is cancelled before a successful payment.

* The order fails to complete based on applicable rules.

**FR-INV-007 — Restock After Manual Refund**
For paid orders, stock may only be restocked after the Store Owner manually updates the refund status to "Refunded" in the system, provided the items are in resellable condition.

### 4.4 Cancellation Management

**FR-CAN-001 — Customer Cancellation Request**
A Customer can submit a cancellation request for their order that has a `processing` status.

**FR-CAN-002 — Cancellation Review**
A Customer's cancellation request must be reviewed by the Store Owner.

**FR-CAN-003 — Cancellation Withdrawal**
A Customer can withdraw their cancellation request as long as the Store Owner has not made a decision.

**FR-CAN-004 — Cancellation Approval**
The Store Owner can approve or reject a Customer's cancellation request.

**FR-CAN-005 — Owner-Initiated Cancellation**
The Store Owner can initiate and immediately confirm the cancellation of a `processing` order without Customer approval.

### 4.5 Manual Refund Management

**FR-REF-001 — Refund Flagging**
A refund requirement is flagged after a paid order is successfully cancelled or a late payment is detected.

**FR-REF-002 — Manual Execution**
Refunds are executed manually by the Store Owner outside the system (e.g., manual bank transfer to the Customer). The system does not communicate with the Payment Gateway for refunds.

**FR-REF-003 — Manual Status Update**
The Store Owner must manually update the refund status in the system to `refunded` once the manual transfer is completed.

**FR-REF-004 — Restock Dependency**
Restock can only be performed when the Store Owner confirms the refund and marks the items as resellable.

**FR-REF-005 — Refund Tracking**
The system must store refund information, including nominal amount, execution time, reason, and status.

### 4.6 Shipping Management

**FR-SHP-001 — Shipping Method**
The system supports two types of shipping methods:

* Manual shipping.

* Shipping through courier integration.

**FR-SHP-003 — Shipping Cost Snapshot**
The shipping cost must be saved as a snapshot at the time the order is created.

**FR-SHP-005 — Tracking Information**
The system must store the tracking number and link it to the correct order.

### 4.7 Shipping Address Snapshot

**FR-ADR-001 — Address Snapshot Creation**
The system must copy the shipping address data into the order when the order is created.

**FR-ADR-002 — Address Immutability**
Changes to the address on the Customer's profile must not change the shipping address of an order that has already been created.

### 4.8 Order History & Tracking

**FR-HIS-001 — Guest Order Tracking**
Guests can view their specific order details and tracking status by inputting their Order ID and the Email/Phone number used during checkout.

## 5. Business Rules

### 5.1 Payment Rules

| ID | Rule | 
| ----- | ----- | 
| PAY-BR-01 | Payments are only made through the payment gateway. | 
| PAY-BR-02 | The payment expiry is 15 minutes. | 
| PAY-BR-03 | Duplicate callbacks must not produce duplicate business effects. | 

### 5.4 Manual Refund Rules

| ID | Rule | 
| ----- | ----- | 
| REF-BR-01 | A refund is required after the cancellation of a paid order is confirmed. | 
| REF-BR-02 | **Refunds are executed manually outside the system by the Store Owner.** | 
| REF-BR-03 | **The `refunded` status is granted only after the Store Owner manually updates it in the admin panel.** | 
| REF-BR-04 | Restock is performed after the refund is marked as successful if items are resellable. | 
| REF-BR-05 | Late payments received must also be flagged for manual refund. | 

## 6. State Transitions

### 6.1 Order State

| Current State | Event | Next State | 
| ----- | ----- | ----- | 
| `pending_payment` | Payment successful | `processing` | 
| `pending_payment` | Payment expired | `expired` | 
| `processing` | Owner cancels with reason | `cancelled` | 
| `processing` | Owner ships order | `shipped` | 
| `shipped` | Order received | `completed` | 

### 6.4 Refund State (Manual Flow)

| Current State | Event | Next State | 
| ----- | ----- | ----- | 
| `not_requested` | Paid order cancelled / Late payment detected | `manual_refund_pending` | 
| `manual_refund_pending` | Store Owner marks as refunded | `refunded` | 

## 7. Acceptance Criteria

### 7.1 Checkout & Order

**AC-TRX-001 — Guest Checkout**
Given a Customer is not logged in

When the Customer provides a valid email and shipping address during checkout

Then the system creates the order as a Guest order and reserves stock.

### 7.4 Refund

**AC-REF-001 — Manual Refund Confirmation**
Given a cancelled paid order is flagged as `manual_refund_pending`

When the Store Owner clicks "Mark as Refunded"

Then the refund state becomes `refunded`.

## 8. Authorization Matrix

| Action | Customer / Guest | Store Owner | 
| ----- | ----- | ----- | 
| Create an order | Yes (Auth & Guest) | No | 
| View own order (Auth) | Yes | Yes | 
| Track order via Order ID (Guest) | Yes | Yes | 
| View all orders | No | Yes | 
| Make a payment | Yes | No | 
| Request cancellation | Yes | No | 
| **Execute & update manual refund** | No | **Yes** | 

**End of Document**