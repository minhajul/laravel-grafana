## Laravel Grafana Observability Stack

A production-ready Docker environment for Laravel 12/13 (PHP 8.4) integrated with a full Observability stack. This
project monitors application performance, host infrastructure health, database metrics, and distributed request tracing
using **Grafana**, **Prometheus**, **Loki**, and **Tempo**.

---

### Architecture

```text
┌─────────────────────────────────── Host / VM ───────────────────────────────────┐
│                                                                                 │
│  ┌────────────────┐      ┌────────────────┐      ┌───────────────────────────┐  │
│  │  Laravel App   ├─────►│  Promtail      │      │  Node Exporter            │  │
│  │  (OTel Trace)  │      │  (Logs Tail)   │      │  (CPU/RAM/Disk/Network)   │  │
│  └──────┬─────────┘      └───────┬────────┘      └─────────────┬─────────────┘  │
│         │                        │                             │                │
│         │                        │                             │                │
│  ┌──────▼─────────┐      ┌───────▼────────┐      ┌─────────────▼─────────────┐  │
│  │  MySQL 8.0     │◄─────┤  MySQL Exporter│      │  Blackbox Exporter        │  │
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

* **App:** Laravel running on PHP 8.4 (via Laravel Sail) with the OpenTelemetry (OTel) PHP extension.
* **Database:** MySQL 8.0.
* **Metrics Scraper:** Prometheus.
* **APM / Distributed Tracing:** Grafana Tempo.
* **Logs Collector:** Promtail & Grafana Loki.
* **Infrastructure Monitoring:** Node Exporter (Host System) & MySQL Exporter (Database Internals).
* **Synthetic Monitoring:** Blackbox Exporter (External HTTP Uptime/Status `Prober`).
* **Visualization:** Grafana (Auto-provisioned with `data sources` and dashboard).

---

### Installation & Setup

1. **Clone the repository** and enter the directory.

2. **Environment Setup:**
   ```bash
   cp .env.example .env
   ```
   Ensure `DB_HOST=mysql`, `DB_CONNECTION=mysql`, and verify your OpenTelemetry keys are present at the bottom of the
   `.env` file.

3. **Start the Stack:**
   This command builds the custom PHP 8.4 image (including the `opentelemetry` PHP extension) and starts all
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

### Services & Ports

All services are accessible on the host machine using these local ports:

| Service               | Local Port              | Internal DNS             | Description                                 |
|:----------------------|:------------------------|:-------------------------|:--------------------------------------------|
| **Laravel App**       | `80` (mapped to `8000`) | `laravel.test:8000`      | Main PHP Application                        |
| **Grafana**           | `3000`                  | `grafana:3000`           | Visualization Dashboard (`admin` / `admin`) |
| **Prometheus**        | `9090`                  | `prometheus:9090`        | Metrics Scraper & TSDB                      |
| **Loki**              | `3100`                  | `loki:3100`              | Log Aggregator                              |
| **Tempo**             | `3200` / `4318`         | `tempo:3200`             | APM / OTel Trace Engine (HTTP receiver)     |
| **MySQL**             | `3306`                  | `mysql:3306`             | MySQL Database                              |
| **Node Exporter**     | `9100`                  | `node-exporter:9100`     | Host VM CPU/RAM metrics                     |
| **MySQL Exporter**    | `9104`                  | `mysql-exporter:9104`    | DB performance variables                    |
| **Blackbox Exporter** | `9115`                  | `blackbox-exporter:9115` | External endpoint uptime check              |

---

### Configuring Grafana

**No configuration is needed!**
On startup, Grafana automatically provisions:

* **Prometheus**, **Loki**, and **Tempo** data sources.
* A preloaded **Laravel Application Observability** dashboard containing real-time application throughput, database
  health, logs, traces, and host hardware resource statistics.

Simply visit **[http://localhost:3000](http://localhost:3000)** and log in with user `admin` and password `admin`.

---

### How to Monitor

#### 1. Real-time Logs (Loki)

Logs are parsed as JSON. In Grafana:

* Go to **Explore** $\rightarrow$ select **Loki** datasource $\rightarrow$ enter `{job="laravel"}` to query application
  log events.

#### 2. Uptime Status (Blackbox)

Verify external availability:

* Query `probe_success{job="blackbox"}`. A value of `1` indicates your Laravel homepage is up and returning `2xx` HTTP
  codes.
* Monitor endpoint latencies using `probe_duration_seconds`.

#### 3. Distributed Tracing / APM (Tempo)

To analyze request trace timelines:

* Go to **Explore** $\rightarrow$ select **Tempo** datasource.
* Search for traces or view request trace waterfalls directly within panels, showing latency spent in middlewares,
  views, and database queries.

#### 4. Host & Database Health

* System health: Query `node_cpu_seconds_total`, `node_memory_Active_bytes`, etc.
* Database performance: Query `mysql_global_status_threads_connected`, `mysql_global_status_slow_queries`, etc.

---

### Data Persistence

This stack uses **Named Volumes** to persist data even if containers are stopped.

* **Database:** `sail-mysql`
* **Logs (Loki):** `loki-data`
* **Metrics (Prometheus):** `prometheus-data`
* **Traces (Tempo):** `tempo-data`
* **Grafana Settings:** `grafana-data`

**To Reset Everything (Caution):**

```bash
./vendor/bin/sail down -v
```

*(The `-v` flag deletes all volumes, wiping databases, historical metrics, and trace logs).*
