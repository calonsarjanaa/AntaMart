# AntaMart UI Implementation Plan

## Purpose

This document defines the end-to-end work to turn AntaMart's transaction API into a usable customer storefront and store-owner console. It complements the backend task list in `TECHNICAL_PLAN.md`; UI task IDs (`UI-00` onward) track the next phase.

The UI will be delivered in small, reviewable tasks. Each implementation task should be committed separately, with its status and commit recorded here.

## Current state

- The Laravel 10 application has Vite, a default Laravel welcome page, and no customer or owner screens.
- `routes/web.php` serves only `/`; transaction routes are JSON endpoints under `/api`.
- Existing backend flows cover checkout, payment-session retry, guest tracking, cancellation, manual refunds, shipments, and audit logging.
- There is no product catalog endpoint or complete product entity. `product_variants` currently contains `product_id`, `name`, SKU, price, stock, and active status, but no product table, description, category, or image.
- Sanctum protects owner API routes, but the app has no login, logout, registration, or owner account screens.
- The UI must treat the webhook-updated order state as authoritative. A redirect from the payment provider alone does not prove payment.

## Product and technical direction

### Proposed UI stack

- Laravel Blade templates and the existing Vite build (`resources/views`, `resources/css`, `resources/js`).
- Server-rendered pages and normal form submissions for core flows, with progressive JavaScript for cart interactions and small usability improvements.
- Same-origin Laravel web routes and controllers, authenticated through Laravel's web session guard and protected by web CSRF middleware. Reuse transaction services and validation rules where possible; avoid making browser code depend on cross-origin API configuration or exposing secrets.
- Responsive, keyboard-accessible layouts with visible focus, labels, validation feedback, and loading/empty/error states.

### Scope assumptions

- Build a guest-first storefront and a role-protected store-owner console.
- Add customer accounts and order history because they are listed in the PRD, but keep guest checkout and guest tracking usable without signing in.
- Store owners manage orders, cancellations, refunds, and shipments. Product authoring and inventory editing are outside the current PRD and are not included here.
- Product catalog work will add the minimum product data needed for real browseable product pages while preserving existing order snapshots and variant stock behavior.
- Shipping remains manual. The UI must show the shipping amount returned/calculated by the server and must not imply carrier quoting or automated label creation.
- Refund execution remains external/manual. The owner console records completion and the resellable item selection; it does not initiate a bank transfer or gateway refund.
- Visual brand assets, final copy, currency presentation, catalog content, and shipping fee policy may need owner input during the relevant task. Until then, use a restrained AntaMart wordmark/text treatment and existing backend values; do not fabricate products or fulfillment promises.

## Task sequence

| Task | Title | Depends on | Status |
|---|---|---|---|
| **UI-00** | UI foundation, route strategy, and shared layout | — | ✅ Implemented |
| **UI-01** | Catalog data and storefront read path | UI-00 | ✅ Implemented |
| **UI-02** | Storefront shell and responsive design system | UI-00 | ✅ Implemented |
| **UI-03** | Product listing, detail, and stock states | UI-01, UI-02 | ✅ Implemented |
| **UI-04** | Shopping cart | UI-02, UI-03 | ✅ Implemented |
| **UI-05** | Guest and customer checkout | UI-01, UI-04 | ✅ Implemented |
| **UI-06** | Payment handoff and order result page | UI-05 | 📋 Pending |
| **UI-07** | Guest order tracking | UI-00, UI-02 | 📋 Pending |
| **UI-08** | Guest cancellation request and withdrawal | UI-07 | 📋 Pending |
| **UI-09** | Customer authentication and account foundation | UI-00 | 📋 Pending |
| **UI-10** | Customer order history and order details | UI-09, UI-06 | 📋 Pending |
| **UI-11** | Store-owner authentication and console shell | UI-00 | 📋 Pending |
| **UI-12** | Owner order list, filters, and order detail | UI-11 | 📋 Pending |
| **UI-13** | Cancellation review and owner-initiated cancellation | UI-12 | 📋 Pending |
| **UI-14** | Manual refund recording and resellable item selection | UI-12 | 📋 Pending |
| **UI-15** | Manual shipment and delivery management | UI-12 | 📋 Pending |
| **UI-16** | Order audit activity view | UI-12 | 📋 Pending |
| **UI-17** | End-to-end UI verification, accessibility, and polish | UI-03–UI-16 | 📋 Pending |

## Detailed tasks and completion criteria

### ✅ UI-00: UI foundation, route strategy, and shared layout

- Replace the Laravel welcome page with a branded storefront entry point.
- Define named web routes, controller/view organization, shared Blade layouts, navigation, flash messages, form error rendering, and reusable buttons/fields/badges.
- Keep customer and owner page route groups distinct. Require the `store_owner` role for all owner pages and mutations.
- Decide the web authentication/session approach and protect against CSRF on state-changing browser forms.
- Add a consistent responsive container, typography, spacing, color tokens, focus states, and accessible semantic landmarks.

**Done when:** the app has a working storefront shell and page navigation; owner pages cannot be opened by guests or customer-role accounts; shared session-status and validation-error presentation is available for later forms.

**Implemented:** Replaced the Laravel welcome page with a responsive storefront landing shell, added shared Blade layout and reusable form-field/status-badge components, flash/error presentation, a role-protected owner placeholder, and HTML 403 handling. Browser pages use the web session and CSRF middleware; API role failures remain JSON. Owner sign-in and forms remain in UI-11; cart interactions remain in UI-04.

### ✅ UI-01: Catalog data and storefront read path

- Design the smallest product entity/catalog representation needed for a real product name, description, and optional image, with one or more existing inventory-backed variants.
- Preserve variant SKU, price, active state, available-stock calculation, and existing order item snapshots.
- Add read-only catalog query/controller behavior that returns only active, purchasable products and their available variants.
- Add development seed data with clearly synthetic content so the storefront can be reviewed locally.
- Keep product authoring/admin CRUD out of this UI scope unless the PRD is expanded.

**Done when:** migrations and seed data create browsable products; inactive or unavailable variants cannot be purchased; existing checkout and snapshot behavior remains intact.

**Implemented:** Added the products table/model and variant relationship, backfilled product records for existing variant IDs, and added a product foreign key on databases that support adding constraints to an existing table (SQLite relies on application-level checks). Added `GET /api/products` with paginated active product/variant data and stock counts, plus synthetic local seed content. Checkout now requires active products and variants and snapshots product description/image data alongside variant details.

### ✅ UI-02: Storefront shell and responsive design system

- Build shared header, footer, navigation, content width, buttons, form fields, product cards, price display, status badges, alert panels, and empty/loading states.
- Ensure usable layout at phone, tablet, and desktop widths.
- Use local/system fonts and local assets by default; avoid adding a remote font/image dependency without need.

**Done when:** storefront pages share a consistent layout, keyboard focus is visible, and the core shell works without horizontal overflow at mobile widths.

**Implemented:** Added reusable alert, empty-state, loading-state, price, and product-card components. The storefront visual direction follows the supplied reference: compact white navigation, blue/cyan hero, light neutral background, small service cards, filter chips, and simple product cards. Avoid invented ratings, discounts, shipping claims, or unsupported categories. Responsive tablet/mobile layouts and reduced-motion behavior are included.

### ✅ UI-03: Product listing, detail, and stock states

- Create a catalog page with product cards, price, variant selection or variant summary, and clear stock/availability state.
- Create a product detail page with description, available variants, price, and stock state.
- Add empty catalog and not-found states. Do not show inactive variants as purchasable.
- Add search/category/filter controls only when backed by available catalog fields; defer fields that do not exist.

**Done when:** a customer can find a seeded product, open its detail page, and see variant price and stock state. Cart and quantity selection are handled in UI-04.

**Implemented:** Added server-rendered product listing and detail routes using active catalog records. Product name/description search and stock-availability filters use existing fields. Product cards show price and stock state without ratings or promotional claims; missing images use a neutral placeholder. Category filters are omitted because the current catalog has no category data.

### UI-04: Shopping cart

- Add/remove variants, change quantity, display line totals and subtotal, and continue shopping.
- Persist only variant IDs and quantities in the Laravel session; read current product names, prices, and stock from the database when rendering the cart.
- Revalidate stock and price on the server at checkout; the cart display is never authoritative for final price or availability.
- Handle unavailable or changed variants without losing the rest of the cart.

**Done when:** cart changes persist in the session, totals recalculate from current database prices, stock changes are surfaced and correctable, and checkout continues to validate the final cart server-side. The checkout handoff/form is completed in UI-05.

**Implemented:** Added session-backed add, update, and remove actions with server-side stock checks, a cart page with current item prices and subtotal, and an item count in the header. Inactive or unavailable items remain removable; the final checkout handoff is left for UI-05.

### ✅ UI-05: Guest and customer checkout

- Collect guest email/phone and required recipient/address fields. No shipping-method control is shown because the current backend calculates shipping without accepting or persisting a method.
- Keep guest checkout available; customer accounts are deferred to UI-09.
- Submit the session cart's variant IDs and quantities with a server-generated idempotency key; the browser cannot override cart prices or quantities.
- Display field-level validation errors and stock conflicts while preserving safe form input.
- Show the current database-priced subtotal and explain that the backend calculates shipping and the final total from the submitted address.
- Redirect to the payment URL when available; if payment-session creation fails, preserve the order and offer a retry for that same order.

**Done when:** valid guest checkout creates one order despite repeated submission; invalid address, unavailable stock, and gateway failure are explained without losing the user's next step.

**Implemented:** Added a responsive guest checkout form, session-cart handoff, server-side idempotency, authoritative catalog/stock repricing, and Xendit payment redirection. A stock race returns the shopper to the cart with an actionable message. If Xendit cannot create a session, the order and reservation are retained and the shopper can retry payment on the same order. Shipping is calculated by the existing backend; selectable methods and pre-submit shipping quotes are not supported by the current contract.

### UI-06: Payment handoff and order result page

- Redirect to the gateway payment URL only when a session URL exists.
- If session creation fails, show order ID, pending-payment state, and a retry action authorized by customer session or matching guest email.
- Provide a return/result page that fetches or rechecks current order/payment state instead of assuming payment from the browser redirect.
- Explain expiry and the next action for a pending, paid, expired, or late-payment/manual-refund state.

**Done when:** all payment states have a clear next step and only backend-confirmed state is presented as paid.

### UI-07: Guest order tracking

- Build a form for order ID and checkout email, call the existing tracking flow, and display order/payment state, items, shipment/tracking, and refund status.
- Preserve the backend's privacy behavior: do not display address, phone, or other contact details in tracking results.
- Handle not-found/mismatch uniformly and explain rate limiting without revealing whether an order ID exists.

**Done when:** matching guest credentials show the limited tracking details; mismatch, invalid input, and rate limiting produce usable messages without leaking personal data.

### UI-08: Guest cancellation request and withdrawal

- From an eligible order view, submit a cancellation reason and exact guest email.
- Show request status and allow withdrawal only while pending and undecided.
- Communicate that approval/rejection is reviewed by the store and that paid-order refunds are handled manually.

**Done when:** eligible guests can submit and withdraw a pending request; duplicate, ineligible, decided, and mismatched-email cases show server errors clearly.

### UI-09: Customer authentication and account foundation

- Add customer registration/login/logout and password reset only if the project chooses to support account creation in this release.
- Keep customer and store-owner roles distinct; customer signup must never grant owner access.
- Support secure session handling, CSRF protection, validation, and useful failure states.

**Done when:** a customer can authenticate, end a session, and cannot access owner routes. If account creation is deferred, explicitly mark authenticated customer UI as deferred and keep guest purchase/tracking fully functional.

### UI-10: Customer order history and order details

- List only the signed-in customer's orders with status, date, total, and clear empty state.
- Show item snapshots, payment/shipment/refund state, and cancellation request state on order detail.
- Permit allowed cancellation request/withdraw actions using the existing business rules.

**Done when:** customers cannot access another customer's order by changing a URL; historical values come from order snapshots.

### UI-11: Store-owner authentication and console shell

- Add/confirm a secure owner sign-in path and role-protected web middleware.
- Build owner navigation, dashboard shell, breadcrumbs, responsive tables, confirmations, and consistent alerts.
- Do not expose owner actions through customer storefront navigation.

**Done when:** only an authenticated `store_owner` can reach owner pages; direct URL access as guest/customer is denied and redirects or shows a clear forbidden page.

### UI-12: Owner order list, filters, and order detail

- Show paginated orders with status, payment state, buyer type/contact summary appropriate to owner access, total, and date.
- Filter by order/payment status and search by order ID/email where supported.
- Show immutable order/address/item snapshots and related cancellation, refund, shipment, and audit state on detail.
- Use pagination and query scopes rather than loading the full order database into a page.

**Done when:** owner can find an order, inspect its timeline and fulfillment state, and no store data is exposed through customer routes.

### UI-13: Cancellation review and owner-initiated cancellation

- Provide a queue of pending cancellation requests and review detail with approve/reject controls.
- Require a rejection reason when rejecting; require a reason for owner-initiated cancellation.
- Confirm consequential actions and refresh displayed state after server response.
- Explain manual refund follow-up for paid orders.

**Done when:** owner actions follow backend status rules, duplicate decisions cannot appear successful, and changed request/order state is reflected in the UI.

### UI-14: Manual refund recording and resellable item selection

- Show refund amount/status and manual-execution guidance.
- Allow owner to record notes and select only eligible order items for restocking.
- Clearly distinguish “record refund completed” from sending money; require a confirmation before submission.
- Display the persisted item selection and completion timestamp after success.

**Done when:** a completed refund is recorded once, only selected eligible items are restocked, and the UI does not imply an automated transfer occurred.

### UI-15: Manual shipment and delivery management

- For processing orders, collect tracking number and optional URL and create the manual shipment.
- For shipped orders, allow an owner to confirm delivery.
- Display current tracking and delivery status on customer and owner order views.
- Clearly label shipment as manual; do not present carrier integration that is not implemented.

**Done when:** server-approved order transitions are reflected and invalid/repeated shipment actions return clear feedback.

### UI-16: Order audit activity view

- Display the order's audit events in chronological order with human-readable labels, actor, time, and safe metadata.
- Avoid rendering secrets or unnecessary personal/payment data from payloads.

**Done when:** owners can understand important payment, inventory, cancellation, refund, and fulfillment events without exposing sensitive callback contents.

### UI-17: End-to-end UI verification, accessibility, and polish

- Verify the primary shopper path: catalog → cart → guest checkout → payment handoff → tracking.
- Verify guest cancellation, customer account/order isolation if account screens are enabled, and owner order/cancellation/refund/shipment operations.
- Cover loading, empty, validation, unauthorized, not-found, rate-limited, and server-error states.
- Review keyboard-only navigation, labels, contrast, focus, semantic headings, and mobile layouts.
- Run existing backend feature tests and relevant frontend/build checks; add UI tests as part of this task.

**Done when:** documented UI flows work end to end against the local application, no role boundary or order ownership check relies only on browser code, and remaining gaps are recorded.

## Delivery milestones

1. **Shopper foundation:** UI-00 through UI-06 (catalog, cart, checkout, payment handoff).
2. **Guest service:** UI-07 and UI-08 (tracking and cancellation).
3. **Accounts and operations:** UI-09 through UI-16 (customer history and store-owner console).
4. **Release readiness:** UI-17 (end-to-end and accessibility review).

The recommended next implementation task is **UI-06**, to add payment return/result states and display webhook-confirmed order/payment status.

