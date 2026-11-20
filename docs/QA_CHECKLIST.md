# Final QA checklist

Mapped to the project brief. Everything marked "automated" is covered by `php artisan test`.

| Requirement | Where | Evidence |
|---|---|---|
| Shipment lifecycle (created, booked, picked up, in transit, delivered, cancelled, reopen) | `ShipmentStateMachine`, `TrackingService` | `ShipmentStateMachineTest`, `TrackingTest` (automated) |
| Tracking timeline, append-only events | shipment page, `TrackingEvent` | `TrackingTest` |
| ETA, delay detection (low/medium/high), editable thresholds | `EtaService`, Settings page | `EtaServiceTest`, `AttentionAndSettingsTest` |
| Exceptions (type, severity, open > investigating > resolved) | `ExceptionService` | `ExceptionServiceTest`, `ExceptionWebTest` |
| Map | shipment page and dashboard (Leaflet + OSM) | `MapAndGeocodeTest`, `DashboardInsightsTest` |
| External API (weather + geocoding) with graceful failure | `WeatherService`, `GeocodingService` | `ExtrasTest`, `MapAndGeocodeTest` |
| Dashboard KPIs and charts | `DashboardService` | `DashboardInsightsTest` |
| Search, filter, sort, pagination | shipment list, API, CSV | `ShipmentFilterTest`, `ShipmentCrudTest` |
| In-app notifications | bell + Notifications page | `NotificationTest` |
| Audit log | admin page + API | `AuditLogTest` |
| REST API v1 (Sanctum) + Postman | `docs/API.md`, `docs/postman/` | `ApiAuthTest`, `ApiShipmentTest` |
| 9 tables | `docs/ERD.md`, migrations | migrations run in every test |
| Roles and authorization | middleware, Policies, scoped services | `RoleMiddlewareTest`, `ShipmentAccessTest`, `AdminAreaTest` |
| Security | `docs/SECURITY.md` | `SecurityTest` |
| Deployment (Docker, Render) | `docs/DEPLOYMENT.md` | `DeploymentTest`; live URL in README |
| CI | `.github/workflows/tests.yml` | green run on GitHub |
| English README, API docs, Postman | `README.md`, `docs/` | manual review |

## Manual smoke test before the demo
1. Open the live URL a couple of minutes early (free hosting sleeps).
2. Sign in as admin, operator and customer (demo box on the login page).
3. Admin: dashboard, shipments, exceptions, audit log, settings.
4. Operator: add a tracking event, report and resolve an exception; check the customer's bell.
5. Customer: only own shipments are visible; open one and check timeline, map and weather.
6. Import the Postman collection and call login, shipments and notifications.
