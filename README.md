## Laravel Grafana Observability Stack

A production-ready Docker environment for Laravel 12 (PHP 8.4) integrated with a full Observability stack. This project
monitors application performance, infrastructure health, and logs using **Grafana**, **Prometheus** and **Loki**.

### Architecture

* **App:** Laravel 12 running on PHP 8.4 (via Laravel Sail).
* **Database:** MySQL 8.0.
* **Metrics:**
    * **Prometheus:** Scrapes metrics from the app and infrastructure.
    * **Laravel Exporter:** Custom instrumentation for Database Query timing.
* **Logs:**
    * **Promtail:** tails `storage/logs/laravel.log` and pushes to Loki.
    * **Loki:** Stores logs for querying.
* **Visualization:** Grafana.

### Directory Structure

Ensure your project config files match this structure for the stack to function correctly:

```text
/laravel-grafana
├── docker-compose.yml
├── .env
├── .docker/
│   ├── 8.4/                # Custom PHP 8.4 Dockerfile & configs
│   └── config/             # Observability Configs
│       ├── grafana.ini
│       ├── local-config.yaml (Loki)
│       ├── prometheus.yml
│       └── promtail.yaml
└── storage/
    └── logs/               # Mounted to Promtail
```

### Installation & Setup

1. **Clone the repository** and enter the directory.

2. **Environment Setup:**

   ```bash
   cp .env.example .env
   ```

   Ensure `DB_HOST=mysql` and `DB_CONNECTION=mysql`.

3. **Start the Stack:**
   This command builds the custom PHP 8.4 image and starts all monitoring containers.

   ```bash
   ./vendor/bin/sail up -d --build
   ```

4. **Install Dependencies & Migrate:**

   ```bash
   ./vendor/bin/sail composer run setup
   ./vendor/bin/sail artisan migrate:fresh --seed
   ```

### Services & Ports

| Service         | Local Address           | Internal Docker Address | Description                         |
|:----------------|:------------------------|:------------------------|:------------------------------------|
| **Laravel App** | `http://localhost:80`   | `laravel.test:80`       | Main Application                    |
| **Grafana**     | `http://localhost:3000` | `grafana:3000`          | Dashboards (Login: `admin`/`admin`) |
| **Prometheus**  | `http://localhost:9090` | `prometheus:9090`       | Metrics Scraper                     |
| **Loki**        | `http://localhost:3100` | `loki:3100`             | Log Aggregation API                 |
| **MySQL**       | `localhost:3306`        | `mysql:3306`            | Database                            |

### Configuring Grafana (First Run)

1. Login to Grafana at **[http://localhost:3000](https://www.google.com/search?q=http://localhost:3000)** (Default:
   `admin` / `admin`).
2. Go to **Connections** $\rightarrow$ **Data Sources** $\rightarrow$ **Add new data source**.

### 1\. Add Loki (Logs)

* **Name:** Loki
* **URL:** `http://loki:3100`  *(Note: Do not use localhost)*
* Click **Save & Test**.

### 2\. Add Prometheus (Metrics)

* **Name:** Prometheus
* **URL:** `http://prometheus:9090`
* Click **Save & Test**.

### How to Monitor

#### 1\. Viewing Logs (Loki)

To debug application errors:

1. Go to **Explore** (Compass Icon) in Grafana.
2. Select **Loki** from the dropdown.
3. Filter by job: `{job="laravel"}`.
4. Click **Run Query** to see real-time logs from `storage/logs/laravel.log`.

#### 2\. Viewing Database Performance (Prometheus)

To see how long SQL queries are taking:

1. Go to **Explore** and select **Prometheus**.
2. Use the following PromQL queries:
    * **95th Percentile Query Latency:**
      `histogram_quantile(0.95, sum(rate(laravel_database_query_duration_seconds_bucket[5m])) by (le))`
    * **Queries Per Second:**
      `sum(rate(laravel_database_query_duration_seconds_count[1m]))`

### Data Persistence

This stack uses **Named Volumes** to persist data even if containers are stopped.

* **Database:** `sail-mysql`
* **Logs (Loki):** `loki-data`
* **Metrics (Prometheus):** `prometheus-data`
* **Grafana Settings:** `grafana-data`

**To Reset Everything (Caution):**
If you want to wipe the database and all metrics/logs to start fresh:

```bash
./vendor/bin/sail down -v
```

*(The `-v` flag deletes all volumes).*

### Troubleshooting

**"Loki: Ingester not ready"**

* Loki takes 30-60 seconds to initialize its storage ring on startup. Wait a minute and refresh.
  **"Laravel Logs not showing in Grafana"**
* Ensure the `storage/logs` directory has read permissions.
* Check Promtail logs: `docker-compose logs -f promtail`.

**"Metrics page is empty"**

* Visit `http://localhost/prometheus`. If it's empty, ensure you have hit the application (`http://localhost`) at least
  once to generate traffic.
