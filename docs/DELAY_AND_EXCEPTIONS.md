# ETA, delay and exception rules

All rules are deterministic (no AI/ML) and live in two services, so they can be unit tested:
`App\Services\EtaService` (delay) and `App\Services\ExceptionService` (exceptions).

## Delay rule

| Input | Meaning |
|---|---|
| `expected_arrival_at` | The planned arrival (the schedule). |
| `estimated_arrival_at` (ETA) | The current estimate. Operators can update it with a reason. |
| `delivered_at` | The real arrival time (only for delivered shipments). |

1. The shipment is **delayed** when the ETA is later than the expected arrival.
2. The **delay** is `ETA - expected arrival`.
3. The **severity** is derived from the delay:

| Delay | Severity |
|---|---|
| more than 0 and up to `delay_low_max_hours` (default 6 h) | LOW |
| more than 6 h and up to `delay_medium_max_hours` (default 24 h) | MEDIUM |
| more than 24 h | HIGH |

Exactly 6 h is still LOW; 6 h 1 min is MEDIUM (the brief says "> 6 hours"). The two thresholds are stored in the
`settings` table (defaults in `config/cargotrace.php`), so they can be changed per group scenario without code changes.

Extra rules:

- A **delivered** shipment is judged by its real delivery time.
- A shipment that has **not arrived** after its expected arrival, with an ETA that was never updated, is treated
  as delayed from that moment ("overdue").
- A **cancelled** shipment has no delay.
- Every assessment returns a human-readable **reason** that shows the values used (ETA, expected arrival, delay, thresholds).

## Exceptions

Types: `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE`.
Severity: `LOW`, `MEDIUM`, `HIGH`. Status flow: `OPEN -> INVESTIGATING -> RESOLVED` (or `OPEN -> RESOLVED`).

- Only staff (admin, operator) report and change exceptions; customers can only read exceptions on their own shipments.
- No exception can be reported on a cancelled shipment.
- Detection time cannot be in the future; resolving needs a note and stamps `resolved_at` and `resolved_by`.
- `RESOLVED` is final.
- Every step adds a marker event to the shipment timeline (`EXCEPTION`, `EXCEPTION_UPDATE`, `EXCEPTION_RESOLVED`,
  `ETA_UPDATED`). Marker events are history like any other event and never block the next physical tracking event.
