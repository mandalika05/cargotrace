# Security

How CargoTrace meets the non-functional security requirements (brief section 6).

| Area | What is done | Where |
|---|---|---|
| Password storage | bcrypt hash (never plain, never returned by the API); minimum 8 characters | `User` casts, `RegisterRequest`, `UserRequest`, `UserResource` |
| Login protection | `throttle:10,1` on web and API login/register, generic error message, session ID regenerated after login (session fixation) | `routes/web.php`, `routes/api_v1.php`, `AuthController` |
| CSRF | `@csrf` on every web form (Laravel verifies the token) | all Blade forms |
| XSS | all output uses escaped `{{ }}`; no `{!! !!}` anywhere; map/chart popups use `textContent` | `resources/views`, `resources/js` |
| SQL injection | Eloquent / query builder bindings only; filter and sort values come from allow-lists | services, `ShipmentFilterRequest`, `AuditLogFilterRequest` |
| Mass assignment | `$fillable` on models; status, number and role are never taken from user input | models, services |
| Authorization | role middleware (`role:`), Policies, and owner scoping inside services (customers only see their own data); server side, not just hidden buttons | `RoleMiddleware`, `app/Policies`, services |
| API | Sanctum bearer tokens that expire (default 7 days), `throttle:60,1`, JSON errors without stack traces or class names | `config/sanctum.php`, `bootstrap/app.php` |
| HTTP headers | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS | `SecurityHeaders` middleware |
| Secrets | only in `.env` (git-ignored); `.env.example` has no real values | `.gitignore`, `.env.example` |
| Audit | important changes recorded with actor, old/new values and IP; passwords never logged; append-only | `AuditService`, `AuditLog` |
| Redirects | notification links only follow local paths (no open redirect) | `NotificationController` |
| Exports | CSV export neutralises spreadsheet formula injection | `ExportController` |

## Production checklist
1. `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`.
2. Serve over HTTPS and set `SESSION_SECURE_COOKIE=true`.
3. A public demo (e.g. the coursework deployment) seeds synthetic data with known demo passwords on purpose (`SEED_DEMO=true`, `SHOW_DEMO_ACCOUNTS=true`). For a real production system turn both off and change every password.
4. `php artisan config:cache route:cache view:cache`.

## Known limits (honest notes)
- No strict Content-Security-Policy yet: the layout has one small inline script and the map loads OpenStreetMap tiles. A CSP with nonces is a possible next step.
- No two-factor login and no email verification (not required by the brief).

## Tests
`tests/Feature/SecurityTest.php` covers headers, rate limits, hashing, XSS escaping, SQL-injection strings, allow-lists, mass assignment, no stack traces, token expiry and guest access to every private page and endpoint. Role and ownership tests are in `AdminAreaTest`, `RoleMiddlewareTest`, `ShipmentAccessTest` and the API tests.
