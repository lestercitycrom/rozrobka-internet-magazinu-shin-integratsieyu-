# Transactional outbox delivery

Only known paid-order message types are processed. Destinations are disabled by default; no HTTP destination is enabled until its schema and data contract are approved. Payloads, exception text and PII are never written to delivery errors; only normalized codes are retained.
