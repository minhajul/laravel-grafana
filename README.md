## Laravel Grafana Observability Stack

[![CI](https://github.com/minhajul/laravel-grafana/actions/workflows/ci.yml/badge.svg)](https://github.com/minhajul/laravel-grafana/actions/workflows/ci.yml)

A local Docker environment for Laravel 13 (PHP 8.4) wired into a full observability stack. It monitors application
performance, host infrastructure health, database metrics, logs and distributed request traces using **Grafana**,
**Prometheus**, **Loki** and **Tempo**.

> The app runs on `artisan serve` with Xdebug available, which is right for local development but not for production.
> Before deploying anything like this, put the app behind PHP-FPM or Octane and review the security notes below.

---

### Architecture

```text
┌─────────────────────────────────── Host / VM ───────────────────────────────────┐
│                                                                                 │
│  ┌────────────────┐      ┌────────────────┐      ┌───────────────────────────┐  │
│  │  Laravel App   ├─────►│  Grafana Alloy │      │  Node Exporter            │  │
│  │  (OTel Trace)  │      │  (Logs Tail)   │      │  (CPU/RAM/Disk/Network)   │  │
│  └──┬──────┬──────┘      └───────┬────────┘      └─────────────┬─────────────┘  │
│     │      │                     │                             │                │
│     │  ┌───▼────────────┐        │                             │                │
│     │  │  Redis         │        │                             │                │
│     │  │  (Metric store)│        │                             │                │
│     │  └────────────────┘        │                             │                │
│  ┌──▼─────────────┐      ┌───────┼────────┐      ┌─────────────▼─────────────┐  │
│  │  MySQL 8.4     │◄─────┤  MySQL Exporter│      │  Blackbox Exporter        │  │
│  │  (Database)    │      │  (DB Metrics)  │      │  (HTTP Uptime Probe)      │  │
│  └────────────────┘      └────────────────┘      └─────────────┬─────────────┘  │
│                                                                │                │
└────────────────────────────────────────────────────────────────┼────────────────┘
                                                                 │
┌────────────────────────────── Monitoring Stack ────────────────┼────────────────┐
│                                                                │                │
│  ┌────────────────┐      ┌────────────────┐      ┌─────────────▼─────────────┐  │
│  │  Grafana Tempo │      │  Grafana Loki  │      │  Prometheus               │  │
│  │  (APM Traces)  │      │  (Log Storage) │      │  (Metrics Storage)        │  │
│  └──────┬─────────┘      └───────┬────────┘      └─────────────┬─────────────┘  │
│         │                        │                             │                │
│         └────────────────────────┼──────────────┬──────────────┘                │
│                                  │              │                               │
│                          ┌───────▼──────────────▼────┐                          │
│                          │  Grafana Dashboard        │                          │
│                          │  (Visualization & Alerts) │                          │
│                          └───────────────────────────┘                          │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

* **App:** Laravel on PHP 8.4 (via Laravel Sail) with the OpenTelemetry (OTel) PHP extension.
* **Database:** MySQL 8.4 LTS.
* **Metric storage:** Redis. Request and query metrics are recorded with atomic Redis updates, so concurrent requests
  never lose samples.
* **Metrics Scraper:** Prometheus.
* **APM / Distributed Tracing:** Grafana Tempo.
* **Logs Collector:** Grafana Alloy & Grafana Loki.
* **Infrastructure Monitoring:** Node Exporter (Host System) & MySQL Exporter (Database Internals).
* **Synthetic Monitoring:** Blackbox Exporter (HTTP uptime probe against `/up`).
* **Visualization & Alerting:** Grafana, auto-provisioned with data sources, a dashboard and alert rules.

---

### Installation & Setup

1. **Clone the repository** and enter the directory.

2. **Environment Setup:**
   ```bash
   cp .env.example .env
   ```
   Ensure `DB_HOST=mysql`, `DB_CONNECTION=mysql` and `REDIS_HOST=redis`. `DB_PASSWORD` must not be empty. Change
   `GRAFANA_ADMIN_PASSWORD` and `MYSQL_EXPORTER_PASSWORD` if the stack is reachable by anyone else.

3. **Start the Stack:**
   This builds the custom PHP 8.4 image (including the `opentelemetry` PHP extension and Node.js) and starts all
   observability containers.
   ```bash
   ./vendor/bin/sail up -d --build
   ```

4. **Install Dependencies & Migrate:**
   ```bash
   ./vendor/bin/sail composer run setup
   ./vendor/bin/sail artisan migrate:fresh --seed
   ```

---

### API Endpoints

| Method & Path                                  | Description                                                                           |
|:-----------------------------------------------|:--------------------------------------------------------------------------------------|
| `GET /`                                        | API info (stateless, no session is created)                                           |
| `GET /api/v1/datapoints?page=1&per_page=20`    | Paginated datapoints (`per_page` 1–100), standard Laravel `data` / `links` / `meta`   |
| `GET /up`                                      | Laravel health check, probed by the Blackbox Exporter                                 |
| `GET /prometheus`                              | Prometheus metrics, restricted to `PROMETHEUS_ALLOWED_IPS`                            |
| `GET /log-test`                                | Writes sample info/error log lines (only registered when `APP_ENV=local`)             |

Each datapoint's `value` is returned typed according to its `cast` column (`string`, `int`, `float`, `bool`).

---

### Services & Ports

Host ports are bound to `127.0.0.1` only. Services without a host port are reachable only by other containers on the
`sail` network.

| Service               | Host Port                      | Internal DNS             | Description                                 |
|:----------------------|:-------------------------------|:-------------------------|:--------------------------------------------|
| **Laravel App**       | `127.0.0.1:80` (→ `8000`)      | `laravel.test:8000`      | Main PHP Application                        |
| **Grafana**           | `127.0.0.1:3000`               | `grafana:3000`           | Dashboards & alerting (login from `.env`)   |
| **Prometheus**        | `127.0.0.1:9090`               | `prometheus:9090`        | Metrics Scraper & TSDB                      |
| **MySQL**             | `127.0.0.1:3306`               | `mysql:3306`             | MySQL Database                              |
| **Redis**             | —                              | `redis:6379`             | Prometheus metric storage                   |
| **Loki**              | —                              | `loki:3100`              | Log Aggregator                              |
| **Alloy**             | —                              | `alloy`                  | Ships `storage/logs` to Loki                |
| **Tempo**             | —                              | `tempo:3200` / `4317` / `4318` | APM / OTel trace engine (API, gRPC, HTTP) |
| **Node Exporter**     | —                              | `node-exporter:9100`     | Host VM CPU/RAM metrics                     |
| **MySQL Exporter**    | —                              | `mysql-exporter:9104`    | DB performance variables                    |
| **Blackbox Exporter** | —                              | `blackbox-exporter:9115` | Endpoint uptime check                       |

---

### Configuration

| Key                                   | Default                         | Purpose                                                                 |
|:--------------------------------------|:--------------------------------|:------------------------------------------------------------------------|
| `PROMETHEUS_ENABLED`                  | `true`                          | Register the `/prometheus` endpoint and the metric collectors           |
| `PROMETHEUS_ALLOWED_IPS`              | loopback + private ranges       | Comma-separated IPs/CIDR ranges allowed to scrape `/prometheus`         |
| `PROMETHEUS_STORAGE`                  | `redis`                         | `redis`, or `memory` (used by the test suite)                           |
| `PROMETHEUS_REDIS_CONNECTION`         | `default`                       | Redis connection from `config/database.php` used for metric storage    |
| `LOG_DAILY_DAYS`                      | `14`                            | Days of `laravel-json-YYYY-MM-DD.log` files to keep                     |
| `GRAFANA_ADMIN_USER` / `_PASSWORD`    | `admin` / `admin`               | Grafana admin login (applied only when `grafana-data` is first created) |
| `MYSQL_EXPORTER_PASSWORD`             | `exporter-password`             | Password of the least-privileged `exporter` MySQL user                  |

In production behind a proxy, set `PROMETHEUS_ALLOWED_IPS` to your scraper's address and configure trusted proxies,
otherwise the private-range default lets all proxied traffic through.

---

### Configuring Grafana

**No configuration is needed.**
On startup, Grafana automatically provisions:

* **Prometheus**, **Loki** and **Tempo** data sources, linked to each other: log lines with a `trace_id` get a
  **View trace** link to Tempo, and spans get a **Logs for this span** link back to Loki.
* The **Laravel Application Observability** dashboard: overview stats, HTTP throughput, p95 latency (overall and per
  route), 5xx error ratio, application query rate and latency by statement type, MySQL status/connections/QPS/slow
  queries, host CPU/memory/disk, the live log stream and recent traces.
* Four alert rules in the **Laravel Observability** folder: *Laravel app down*, *MySQL down*, *High HTTP 5xx error
  ratio* and *High HTTP p95 latency*. No contact points are configured, so alerts show in Grafana's alerting UI until
  you add one.

Visit **[http://localhost:3000](http://localhost:3000)** and log in with `GRAFANA_ADMIN_USER` /
`GRAFANA_ADMIN_PASSWORD`. To change the password later: `./vendor/bin/sail exec grafana grafana cli admin reset-admin-password '<new>'`.

---

### How to Monitor

#### 1. Application Metrics (Prometheus)

* `laravel_http_request_duration_seconds{method,status,uri}`: request latency histogram, labelled by route template.
* `laravel_database_query_duration_seconds{sql_type}`: query latency histogram with sub-millisecond buckets;
  `sql_type` is one of `select`, `insert`, `update`, `delete`, `other`.
* `app_users_count`, `app_datapoints_count`: gauges.

HTTP requests, artisan commands and queue workers are all measured.

#### 2. Real-time Logs (Loki)

The app writes JSON logs to `storage/logs/laravel-json-YYYY-MM-DD.log`; Alloy ships them to Loki with the original
event timestamp and `level` / `channel` labels. In Grafana:

* Go to **Explore** → select **Loki** → enter `{job="laravel"}`.

#### 3. Uptime Status (Blackbox)

* Query `probe_success{job="blackbox"}`. A value of `1` means `/up` is returning `2xx`.
* Monitor endpoint latencies using `probe_duration_seconds`.

#### 4. Distributed Tracing / APM (Tempo)

* Go to **Explore** → select **Tempo**, or use the **Recent Traces** dashboard panel.
* Trace waterfalls show time spent in middleware, views and database queries.

#### 5. Host & Database Health

* System health: `node_cpu_seconds_total`, `node_memory_MemAvailable_bytes`, etc.
* Database performance: `mysql_up`, `mysql_global_status_threads_connected`, `mysql_global_status_slow_queries`, etc.

---

### Testing

```bash
./vendor/bin/sail artisan test --compact
```

The test suite uses in-memory SQLite and in-memory metric storage, and disables OpenTelemetry export. CI runs Pint,
the test suite, `composer audit` and `promtool check config` on pushes to `main` and on pull requests.

---

### Data Persistence

This stack uses **Named Volumes** to persist data even if containers are stopped.

* **Database:** `sail-mysql`
* **Metric storage (Redis):** `sail-redis`
* **Logs (Loki):** `loki-data`
* **Log shipping positions (Alloy):** `alloy-data`
* **Metrics (Prometheus):** `prometheus-data`
* **Traces (Tempo):** `tempo-data`
* **Grafana Settings:** `grafana-data`

**To Reset Everything (Caution):**

```bash
./vendor/bin/sail down -v
```

*(The `-v` flag deletes all volumes, wiping databases, historical metrics, and trace logs).*

---

### Upgrading an Existing Install

If you ran an earlier version of this stack (MySQL 8.0, Promtail, Grafana as root), either reset everything with
`./vendor/bin/sail down -v`, or migrate in place:

1. **Back up the database.** The MySQL 8.0 → 8.4 upgrade is one-way.
2. **Convert MySQL users** while still on 8.0. MySQL 8.4 no longer loads `mysql_native_password` by default:
   ```bash
   ./vendor/bin/sail exec mysql mysql -uroot -p"$DB_PASSWORD" -e "
     ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '<DB_PASSWORD>';
     ALTER USER 'root'@'%' IDENTIFIED WITH caching_sha2_password BY '<DB_PASSWORD>';
     ALTER USER 'sail'@'%' IDENTIFIED WITH caching_sha2_password BY '<DB_PASSWORD>';"
   ```
3. **Add the new `.env` keys** from `.env.example` (`REDIS_HOST=redis`, `PROMETHEUS_*`, `GRAFANA_ADMIN_*`,
   `MYSQL_EXPORTER_PASSWORD`, `LOG_DAILY_DAYS`).
4. **Let Grafana's default user own its existing volume** (it used to run as root):
   ```bash
   docker compose run --rm --no-deps --user root --entrypoint chown grafana -R 472:0 /var/lib/grafana
   ```
5. **Rebuild and restart**, removing the old Promtail container:
   ```bash
   ./vendor/bin/sail build --no-cache
   ./vendor/bin/sail up -d --remove-orphans
   ```
6. **Create the MySQL exporter user** (init scripts only run on an empty volume):
   ```bash
   ./vendor/bin/sail exec mysql bash /docker-entrypoint-initdb.d/20-create-exporter-user.sh
   ```
7. **Run the new migrations** (queue tables): `./vendor/bin/sail artisan migrate`.
8. If the `tempo-data` volume holds Tempo 2.x data and Tempo fails to start, remove that volume.
