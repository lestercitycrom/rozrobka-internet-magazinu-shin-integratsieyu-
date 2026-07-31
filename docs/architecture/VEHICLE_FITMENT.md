# Vehicle fitment boundary

The VehicleFitment client is disabled by default and never calls remotely without both explicit enablement and an API key. Registrations are normalized in memory, cache keys are SHA-256 hashes with a short TTL, and no raw registration, VIN, owner data or response body is persisted or logged.

The technical lookup endpoint is `https://www.vegvesen.no/ws/no/vegvesen/kjoretoy/felles/datautlevering/enkeltoppslag/kjoretoydata`. Statens vegvesen documents API-key access, a 50,000-calls-per-key daily limit, and approved tire/rim combinations. Header name is configurable because the public overview confirms an API key but this repository does not embed an unverified Swagger header contract. The UI must not assert compatibility; it offers manual catalog selection whenever disabled or unavailable.

Official sources: [technical API](https://www.vegvesen.no/nn/fag/teknologi/data-og-api-ar/et-utvalg-apne-data/api-for-tekniske-koyretoyopplysingar/), [endpoint metadata](https://dataut.vegvesen.no/dataservice/kjoretoyopplysninger-tekniske-data-enkeltoppslag), and [usage/privacy restrictions](https://www.vegvesen.no/kjoretoy/kjop-og-salg/kjoretoyopplysninger/sjekk-kjoretoyopplysninger/om-tjenesten/?lang=nb).
