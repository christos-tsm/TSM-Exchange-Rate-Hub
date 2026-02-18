# TSM Exchange Rate Hub

A production-grade WordPress plugin that provides a centralized, maintainable, and extensible way to manage and display currency exchange rates.

---

## Quick Start

```bash
git clone <repository-url>
cd tsm-exchange-hub
docker compose up
```

Open **http://localhost:8080** and complete the WordPress installation wizard.

Then navigate to **Plugins → Installed Plugins**, activate **TSM Exchange Rate Hub**, and go to **Exchange Rates → Dashboard** to fetch your first batch of rates.

---

## Plugin Architecture

```
plugins/tsm-exchange-rate-hub/
├── tsm-exchange-rate-hub.php          # Bootstrap & constants
├── uninstall.php                      # Full cleanup on deletion
│
├── includes/                          # Core logic (shared)
│   ├── class-…-activator.php          # Table creation, defaults, cron scheduling
│   ├── class-…-deactivator.php        # Cron cleanup (data preserved)
│   ├── class-…-loader.php             # WPPB hook orchestrator
│   ├── class-…-i18n.php               # i18n / text-domain
│   ├── class-…-db.php                 # Custom DB tables CRUD
│   ├── class-…-api.php                # External API integration
│   ├── class-…-cache.php              # Transient-based caching
│   ├── class-…-cron.php               # WP-Cron scheduling
│   ├── class-…-rest.php               # REST API endpoints 
│   └── class-…-cli.php                # WP-CLI commands 
│
├── admin/                             # Back-office UI
│   ├── class-…-admin.php              # Menu pages, Settings API, AJAX
│   ├── partials/                      # PHP view templates
│   ├── css/                           # Admin styles
│   └── js/                            # Admin JS (refresh, select-all)
│
├── public/                            # Front-end UI
│   ├── class-…-public.php             # Shortcode + page template
│   ├── partials/                      # Shortcode output template
│   ├── css/                           # Public styles
│   └── js/                            # Public JS
│
└── templates/                         # Theme integration
    └── page-exchange-rates.php        # Custom page template
```

The plugin follows the **WordPress Plugin Boilerplate** (WPPB) pattern with clear separation of concerns:

| Layer | Responsibility |
|-------|---------------|
| **API** | Encapsulates HTTP requests to ExchangeRate-API |
| **DB** | Custom tables for latest + historical data |
| **Cache** | WordPress Transients to prevent redundant reads |
| **Cron** | WP-Cron for periodic automatic updates |
| **Admin** | Settings page + dashboard (Settings API, AJAX, nonces) |
| **Public** | `[tsm_exchange_rates]` shortcode + page template |
| **REST** | `/wp-json/tsm-exchange-rate-hub/v1/rates`  |
| **CLI** | `wp tsm-erh` commands for terminal management  |

---

## Data Storage Decisions

### Why Custom Tables (not CPT)

| Criteria | Custom Tables | Custom Post Type |
|----------|--------------|-----------------|
| Query performance | Direct SQL, indexed columns | `WP_Query` overhead, `meta_query` |
| Schema control | Explicit types, UNIQUE keys, decimal precision | Schemaless `postmeta` (everything is `longtext`) |
| Historical data | Append-only table with composite index | Would need thousands of posts |
| Data size | Compact, no wp_posts bloat | Pollutes core tables |

**Decision:** Custom tables are the right choice for structured, numeric, time-series data.

### Schema

**`wp_tsm_exchange_rates`** — Latest rates (one row per currency pair)

| Column | Type | Notes |
|--------|------|-------|
| `id` | `BIGINT UNSIGNED` | PK, auto-increment |
| `base_currency` | `VARCHAR(3)` | ISO 4217 code |
| `target_currency` | `VARCHAR(3)` | ISO 4217 code |
| `rate` | `DECIMAL(20,10)` | High precision for financial data |
| `last_updated` | `DATETIME` | UTC |
| | `UNIQUE KEY` | `(base_currency, target_currency)` — enables `REPLACE INTO` upserts |

**`wp_tsm_exchange_rates_history`** — Append-only time-series

| Column | Type | Notes |
|--------|------|-------|
| `id` | `BIGINT UNSIGNED` | PK, auto-increment |
| `base_currency` | `VARCHAR(3)` | |
| `target_currency` | `VARCHAR(3)` | |
| `rate` | `DECIMAL(20,10)` | |
| `recorded_at` | `DATETIME` | When the rate was recorded |
| | `KEY` | `(base_currency, target_currency, recorded_at)` — optimizes range queries |

---

## Caching Strategy

```
Request → Transient Cache (hit?) → YES → return cached data
                                   NO  → DB query → populate cache → return
```

- **Mechanism:** WordPress Transients API (`set_transient` / `get_transient`)
- **TTL:** Matches the configured update frequency (default: 60 minutes)
- **Invalidation:** Automatic on expiry; forced clear on settings change or manual refresh
- **Object cache upgrade:** If Redis/Memcached is available, WordPress transparently upgrades transients to the object cache backend — zero code changes needed

### Reducing API Calls

1. **Scheduled fetches** — WP-Cron runs every N minutes (configurable, default 60)
2. **Transient cache** — Front-end reads never hit the API; they read cache → DB
3. **Manual refresh** — Admin dashboard button for on-demand updates
4. **Cache invalidation** — Changing settings clears the cache and reschedules cron

---

## External API

**Provider:** [ExchangeRate-API](https://www.exchangerate-api.com/) (free tier)

- **Endpoint:** `https://open.er-api.com/v6/latest/{BASE}`
- **No API key required** for the free tier
- **Rate limit:** 1,500 requests/month (free) — more than enough with cron-based fetching
- **Response:** JSON with `rates` object keyed by ISO 4217 currency codes

---

## Security

- **Nonces** — All admin AJAX actions use `check_ajax_referer()`
- **Capability checks** — `current_user_can('manage_options')` on every privileged action
- **Input sanitization** — `sanitize_text_field()`, `absint()`, whitelist validation
- **Output escaping** — `esc_html()`, `esc_attr()` on every output
- **Prepared statements** — All DB queries use `$wpdb->prepare()`
- **Direct access prevention** — `WPINC` / `WP_UNINSTALL_PLUGIN` guards on every file

---

## Features

### Admin Dashboard
- Status cards: base currency, last updated, next scheduled update, active currencies, refresh interval
- Manual "Refresh Rates Now" button (AJAX with loading state)
- Full rates table with currency name, code, rate, and timestamp
- Shortcode usage reference

### Settings Page
- Base currency dropdown (35+ currencies)
- Update frequency input (5–1440 minutes)
- Currency grid with checkboxes + Select All / Deselect All
- Standard WordPress Settings API integration

### Shortcode: `[tsm_exchange_rates]`

| Attribute | Default | Description |
|-----------|---------|-------------|
| `base` | Configured base | Override base currency |
| `currencies` | All enabled | Comma-separated filter, e.g. `"USD,GBP,JPY"` |
| `title` | "Exchange Rates" | Custom heading |
| `show_updated` | `yes` | Show "Last updated" timestamp |

### Page Template
Select **Exchange Rates** from the Page Attributes → Template dropdown to create a dedicated exchange rates page that inherits the active theme's header, footer, and styles.

### REST API 

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/wp-json/tsm-exchange-rate-hub/v1/rates` | Public | Latest rates (optional `?base=USD`) |
| `GET` | `/wp-json/tsm-exchange-rate-hub/v1/rates/{BASE}` | Public | Rates for specific base |
| `POST` | `/wp-json/tsm-exchange-rate-hub/v1/refresh` | Admin | Force refresh from API |

### WP-CLI Commands 

All commands are registered under the `tsm-erh` namespace.

```bash
# Show current exchange rates
wp tsm-erh rates
wp tsm-erh rates --base=USD --format=json

# Force refresh from API
wp tsm-erh refresh
wp tsm-erh refresh --base=USD

# Plugin status overview
wp tsm-erh status

# Clear transient cache
wp tsm-erh cache clear

# View historical rates for a currency pair
wp tsm-erh history EUR USD
wp tsm-erh history EUR GBP --limit=50 --format=csv
```

| Command | Description |
|---------|-------------|
| `wp tsm-erh rates` | Display latest rates (supports `--base`, `--format`) |
| `wp tsm-erh refresh` | Fetch & store fresh rates from API |
| `wp tsm-erh status` | Show base currency, last update, next cron, cache status |
| `wp tsm-erh cache clear` | Purge all plugin transients |
| `wp tsm-erh history <base> <target>` | Show historical rate records (supports `--limit`, `--format`) |

---

## Docker Setup

The root `docker-compose.yml` provides:

| Service | Image | Port |
|---------|-------|------|
| `db` | `mysql:8.0` | Internal only |
| `wordpress` | `wordpress:latest` | `8080` → `80` |

The plugin directory is bind-mounted so code changes reflect immediately.

```bash
# Start
docker compose up -d

# Stop
docker compose down

# Reset (destroy data)
docker compose down -v
```

---

## Assumptions & Trade-offs

1. **Free API tier** — The free ExchangeRate-API has a 1,500 req/month limit. With hourly updates this uses ~720/month, well within limits.
2. **WP-Cron** — Relies on site traffic to trigger. For low-traffic sites, a real system cron hitting `wp-cron.php` is recommended.
3. **No tests included** — Prioritized a complete, production-quality implementation. Testing would use WP_Mock for unit tests and `wp-env` for integration tests. Key areas to test: API error handling, cache hit/miss paths, DB schema migrations, shortcode attribute parsing.
4. **Single base currency** — The plugin fetches rates for one base currency at a time. Multi-base support could be added by looping through an array of bases in the cron callback.
5. **Shortcode over Gutenberg** — Chosen for broader theme compatibility and simpler implementation. A Gutenberg block could wrap the same rendering logic in a future iteration.

---

## License

GPL-2.0+ — See [LICENSE.txt](plugins/tsm-exchange-rate-hub/LICENSE.txt)
