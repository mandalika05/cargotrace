# CargoTrace REST API v1

Base URL: `/api/v1` (for example `http://localhost:8000/api/v1`). All answers are JSON.
A ready-to-import Postman collection is in `docs/postman/CargoTrace.postman_collection.json`.

## Authentication (Laravel Sanctum)

1. `POST /auth/login` with `{ "email", "password", "device_name"? }` returns `{ "token_type": "Bearer", "token": "...", "user": {...} }`.
2. Send the token on every other request: `Authorization: Bearer <token>`.
3. `POST /auth/register` creates a **customer** (the role can never be chosen). `GET /auth/me`, `POST /auth/logout` (revokes the token).

Login and register are limited to 10 attempts per minute, other endpoints to 60 per minute.

## Permissions

The API uses the **same Policies, Form Requests and Services as the web app**, so the rules are identical.

| Endpoint | Admin | Operator | Customer |
|---|---|---|---|
| `GET /shipments`, `GET /shipments/{id}`, `/tracking`, `/delay`, `/exceptions` | all | all | own shipments only |
| `POST /shipments`, `PUT /shipments/{id}` (not finished), `POST /shipments/{id}/tracking` | yes | yes | no (403) |
| `DELETE /shipments/{id}`, `POST /shipments/{id}/reopen` (reason required) | yes | no | no |
| `PATCH /shipments/{id}/eta` (reason required) | yes | yes | no |
| `POST /shipments/{id}/exceptions`, `PATCH /exceptions/{id}` | yes | yes | no |
| `GET /exceptions`, `GET /dashboard` | all | all | own only |
| `GET /attention`, `GET /carriers`, `GET /locations`, `GET /settings/delay` | yes | yes | no |
| `PUT /settings/delay` | yes | no | no |

## Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/shipments?q=&status=&carrier_id=&sort=&per_page=` | search, filter, sort (`newest, oldest, eta_asc, eta_desc, number`), pagination (`per_page` 1-50) |
| POST / PUT | `/shipments`, `/shipments/{id}` | body as the web form, with `items[]`; `status` cannot be forced |
| GET | `/shipments/{id}/tracking` | chronological timeline + `meta.current_location` |
| POST | `/shipments/{id}/tracking` | `event_type` follows the status state machine; invalid jumps give 422 |
| GET | `/shipments/{id}/delay` | delay state, severity, minutes, **reason** and thresholds used |
| PATCH | `/shipments/{id}/eta` | `{ estimated_arrival_at, reason }`; logged in the timeline |
| GET | `/attention` | "Needs attention" queue, most serious first |
| GET / POST / PATCH | `/exceptions`, `/shipments/{id}/exceptions`, `/exceptions/{id}` | status `OPEN -> INVESTIGATING -> RESOLVED`; resolving needs `note` |
| GET | `/shipments/{id}/weather` | 5-day forecast (current position + destination) from Open-Meteo; `available=false` when offline |
| GET | `/dashboard` | totals, by status, delayed, exception counts |
| GET / PUT | `/settings/delay` | `delay_low_max_hours`, `delay_medium_max_hours` |

## Error format

| Code | Meaning | Body |
|---|---|---|
| 401 | missing or wrong token | `{ "message": "Unauthenticated." }` |
| 403 | role not allowed or not your data | `{ "message": "..." }` |
| 404 | unknown id | `{ "message": "Resource not found." }` |
| 422 | validation or business rule failed | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 429 | too many requests | `{ "message": "Too Many Attempts." }` |
