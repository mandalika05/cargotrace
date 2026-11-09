# Requirements

## 1. Scope

In scope: shipment lifecycle, tracking timeline and map, ETA/delay rules, exception management,
role-based access control, REST API, external API, dashboard, testing, deployment.

Out of scope: real carrier/customs integration, payments, real-time GPS hardware, machine learning, microservices.
Demo data is synthetic but consistent and documented.

## 2. Roles and permissions

| Capability | Admin | Operator | Customer |
|---|:-:|:-:|:-:|
| Manage users, settings (delay rules) | ✔ | | |
| Manage carriers and locations | ✔ | | |
| View audit log | ✔ | | |
| Create / update shipments (not terminal) | ✔ | ✔ | |
| Delete shipments | ✔ | | |
| Add tracking events | ✔ | ✔ | |
| Create / update exceptions | ✔ | ✔ | |
| View all shipments and dashboard | ✔ | ✔ | |
| View own shipments, ETA, timeline, map | | | ✔ |
| Receive notifications | | | ✔ |

Authorization is enforced on the server (middleware + Laravel Policies), never only by hiding buttons.

## 3. Use cases

| ID | Actor | Use case |
|---|---|---|
| UC-01 | Anyone | Register as customer, sign in, sign out |
| UC-02 | Admin | Manage users, carriers, locations, delay thresholds |
| UC-03 | Operator | Create a shipment with its cargo items |
| UC-04 | Operator | Advance shipment status along the allowed transitions |
| UC-05 | Operator | Add a tracking event (location, time, description) |
| UC-06 | Operator | Report, investigate and resolve an exception |
| UC-07 | Operator | Work the "Needs attention" queue (delayed or exception shipments) |
| UC-08 | Customer | Follow own shipment: progress, ETA, map, timeline |
| UC-09 | Customer | Read notifications about status changes and exceptions |
| UC-10 | Admin | Review the audit log |
| UC-11 | Anyone | Look up a shipment by tracking number (public page, extra feature) |

## 4. Business rules

**Status lifecycle.** `CREATED → BOOKED → PICKED_UP → IN_TRANSIT → DELIVERED`. `CANCELLED` is allowed only
from `CREATED` or `BOOKED`. `DELIVERED` and `CANCELLED` are terminal. Re-opening a terminal shipment is a
special Admin procedure that requires a reason and is written to the audit log. Transitions are validated in
the backend (`ShipmentService`).

**Tracking events** are history: they can be added but never edited or deleted. An event time cannot be earlier
than the previous event or in the future, and its status change must follow a valid transition.

**ETA and delay.** `delay = estimated_arrival − expected_arrival`. If delay > 0 the shipment is delayed.
Severity is read from admin-configurable settings (defaults, based on scenario Group A):

| Delay | Severity |
|---|---|
| ≤ 6 h | LOW |
| > 6 h and ≤ 12 h | MEDIUM |
| > 12 h | HIGH |

The screen always shows the values used (expected, estimated, delay hours, thresholds). Weather data is
informational only and is never presented as a delay prediction.

**Exceptions.** Types: `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`,
`DOCUMENTATION_ISSUE`. Each has severity, status `OPEN → INVESTIGATING → RESOLVED`, detected time and resolved
time. A new exception appears on the timeline and notifies the customer.

**Audit log** records actor, action, time and entity for important changes.

## 5. Scenario configuration (editable in one place)

Defaults follow Group A until the lecturer provides the real values:
delay threshold 12 h, main exception `CUSTOMS_HOLD`, KPI on-time delivery rate, route Southeast Asia → Europe.
All thresholds live in the `settings` table so a requirement change is a data change, not a code change.

## 6. Non-functional requirements

Passwords hashed; CSRF, XSS and SQL-injection protection; mass-assignment protection; secrets only in `.env`;
JSON API with proper status codes (200/201/401/403/404/422) and no stack traces; server-side pagination;
responsive UI; automated tests; live deployment.
