# Database design (ERD)

Ten tables: the nine required by the brief plus `settings` for configurable delay rules.
In-app notifications use Laravel's built-in `notifications` table (polymorphic to the user).

```mermaid
erDiagram
    USERS ||--o{ SHIPMENTS : "customer_id"
    USERS ||--o{ SHIPMENTS : "created_by"
    CARRIERS ||--o{ SHIPMENTS : "carrier_id"
    LOCATIONS ||--o{ SHIPMENTS : "origin_id"
    LOCATIONS ||--o{ SHIPMENTS : "destination_id"
    SHIPMENTS ||--|{ SHIPMENT_ITEMS : "has"
    SHIPMENTS ||--o{ TRACKING_EVENTS : "has"
    SHIPMENTS ||--o{ SHIPMENT_EXCEPTIONS : "has"
    LOCATIONS ||--o{ TRACKING_EVENTS : "location_id (optional)"
    USERS ||--o{ TRACKING_EVENTS : "created_by"
    USERS ||--o{ AUDIT_LOGS : "actor"
    USERS ||--o{ NOTIFICATIONS : "notifiable"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string role "admin|operator|customer"
    }
    CARRIERS {
        bigint id PK
        string name
        string code UK
        boolean is_active
    }
    LOCATIONS {
        bigint id PK
        string name
        string city
        string country
        decimal latitude
        decimal longitude
    }
    SHIPMENTS {
        bigint id PK
        string shipment_number UK
        bigint customer_id FK
        bigint carrier_id FK
        bigint origin_id FK
        bigint destination_id FK
        string cargo_type
        decimal weight_kg
        decimal volume_cbm
        datetime expected_departure_at
        datetime expected_arrival_at
        datetime estimated_arrival_at
        string status
        datetime deleted_at
    }
    SHIPMENT_ITEMS {
        bigint id PK
        bigint shipment_id FK
        string description
        int quantity
        decimal weight_kg
    }
    TRACKING_EVENTS {
        bigint id PK
        bigint shipment_id FK
        string event_type
        bigint location_id FK
        decimal latitude
        decimal longitude
        datetime occurred_at
        text description
        bigint created_by FK
    }
    SHIPMENT_EXCEPTIONS {
        bigint id PK
        bigint shipment_id FK
        string type
        string severity
        string status
        datetime detected_at
        datetime resolved_at
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string entity_type
        bigint entity_id
        json old_values
        json new_values
    }
    SETTINGS {
        bigint id PK
        string key UK
        string value
    }
```

## Cardinality and design decisions

- **User → Shipments (1:N).** A customer owns many shipments; each shipment has exactly one customer. `created_by` records the staff member.
- **Carrier → Shipments (1:N).** A carrier handles many shipments.
- **Location → Shipments (1:N twice).** Origin and destination are separate foreign keys to the same table.
- **Shipment → Items (1:N, at least one).** Items are created together with the shipment inside one database transaction.
- **Shipment → Tracking events (1:N).** Append-only history; no update or delete.
- **Shipment → Exceptions (1:N).** A shipment can have several exceptions over its life.
- **Soft delete** only on `shipments`, because history must survive an Admin delete. Other tables do not need it.
- **Indexes** on `shipments.status`, `shipments.customer_id`, `shipments.expected_arrival_at`, `tracking_events(shipment_id, occurred_at)`, `users.role`.
- **Unique** `shipments.shipment_number`, `carriers.code`, `users.email`, `settings.key`.
- Coordinates stored as `decimal(10,7)` consistently for every table.
