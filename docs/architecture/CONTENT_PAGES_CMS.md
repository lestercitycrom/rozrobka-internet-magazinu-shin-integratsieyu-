# Information pages CMS

`ContentPage` is independent from `ServicePage` and stores ordinary information-page content in Norwegian Bokmål and Russian: stable unique slug, editorial order, draft/published status, title, excerpt, body, meta title and meta description.

`PublishedContentPageReadService` is the public boundary. It returns a page only when published and only in the requested locale. The public routes are deliberately allowlisted: `/om-oss`, `/kontakt`, `/personvern`, and `/kjopsvilkar`; matching Russian preview routes live under `/ru`. The existing locale middleware keeps the preview environment-bound, noindex and canonical to its Norwegian URL.

The content-page admin resource supports create, edit, publication status and deletion. Its seed uses `firstOrCreate`; all initial pages are draft review placeholders. No contact details or legal terms are supplied until confirmed editorial copy exists.
