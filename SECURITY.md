# Security Policy

## Supported versions

The latest major version receives security fixes.

## Reporting a vulnerability

Please **do not** open a public issue for a security problem.

Prefer [GitHub's private vulnerability reporting](https://github.com/cboxdk/laravel-queue-monitor/security/advisories/new)
(the **Report a vulnerability** button under the repository's Security tab).
If you cannot use it, email [sn@cbox.dk](mailto:sn@cbox.dk) instead. Include a
description and, where possible, a proof of concept. You will get a response
within a few business days; this is a best-effort process, not a contractual
SLA.

## Areas of particular interest for this package

- **The dashboard and REST API authorization gate** — the package denies by
  default outside `local`, enforced by `EnsureQueueMonitorEnabled` on every
  route; report any way to reach job data or a mutating action past it.
- **Payload and exception redaction** — job payloads, tags, and exception
  data are redacted against `api.sensitive_keys` before serialization. Report
  any endpoint that returns unredacted sensitive data (the drill-down summary
  and the job-detail views are the surfaces to probe).
- **Job payload deserialization** — the queued-job capture path unserializes
  the stored command restricted to its declared class. Report any object
  injection that escapes that restriction.
- **Export output** — CSV/JSON exports escape formula-injection vectors.
  Report any spreadsheet-formula or injection that survives.
- **Destructive endpoints** — replay, delete, batch, prune, and stuck-job
  resolution. Report any way to widen their scope past the validated filters
  or the configured caps.
