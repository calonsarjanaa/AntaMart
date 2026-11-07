# Known Limitations and Operational Notes

This document records the limits of the current transaction and fulfillment MVP. It describes implemented behavior; it is not a promise of production readiness.

## Product and workflow limits

- **Single payment provider:** checkout creates Xendit invoices. There is no provider selection, payment-method orchestration, or fallback provider.
- **Basic product catalog:** products contain a name, description, optional image path, active flag, and sort order. There are no categories, product search metadata, image-management UI, or product-authoring screens. Local seed products are synthetic examples.
- **Gateway outage:** if invoice creation fails, checkout still creates the order and reserves inventory. The order remains `pending_payment`; retry `POST /api/orders/{id}/payment-session` after the gateway recovers. A retry must use the authorized customer or the matching guest email.
- **Callback processing:** the webhook controller processes the callback synchronously before returning `200`. There is no separate durable callback queue or dead-letter workflow. Duplicate payment IDs are deduplicated by the application.
- **Payment expiry worker:** order expiry is scheduled on the database queue. The `queue:work database` process must be supervised and running; delayed jobs do not run merely because the scheduler is active.
- **Manual fulfillment:** shipments are entered by a store owner, and delivery is confirmed manually. There is no carrier API, label purchasing, or carrier event polling.
- **Manual refunds:** the API records that a refund was executed externally. It does not call a payment provider to issue money. Resellable items are selected by order-item ID; only those eligible items are restocked.
- **Cancellation review:** only one pending request per order is allowed. Paid cancellation does not restock automatically; a refund must be marked and selected items restocked through the refund workflow.
- **Guest tracking:** lookup requires order ID and the exact guest email, returns a limited order summary, and is rate limited to 10 requests per minute per IP. It is not an authenticated order-management feature.
- **Authentication provisioning:** this API defines Sanctum-protected owner routes but does not provide registration, login, token issuance, or role-management endpoints.
- **Currency and shipping:** prices and shipping costs are captured in the order, but there is no currency negotiation, tax engine, shipping-rate quote, or carrier integration in this MVP.

## Operations and security

- Keep `APP_DEBUG=false` in production and use HTTPS. Do not commit `.env` or real payment credentials.
- Set a strong `XENDIT_WEBHOOK_TOKEN`; configure the matching value in the Xendit callback settings. Invalid webhook tokens are rejected by middleware.
- Use a persistent production database and queue backend, run migrations as a controlled deployment step, and supervise queue workers so delayed expiry jobs are processed.
- Configure backups, monitoring, log retention, alerting, and a worker restart/deployment policy in the hosting environment; these are not supplied by this repository.
- The guest tracking endpoint intentionally omits address and contact details. Do not extend its response with personal data without reviewing the disclosure and abuse risks.

## Verification notes

- The feature suite exercises the documented transaction flows and implementation-derived edge cases. The roadmap mentions “16 identified edge cases”, but no enumerated source list exists in the repository; exact mapping to those 16 cases remains unverified.
- The current PHPUnit XML file emits a deprecation warning about its schema. This does not fail the tests; migrate the XML configuration when convenient.
- API examples and the OpenAPI document should be kept aligned with `app/routes/api.php` and the controller validation rules when endpoints change.

