# Laravel Grafana
A straightforward docker-compose setup to launch a Laravel application alongside Grafana, Prometheus, and Loki containers. This project aims to monitor the performance of a Laravel application using Grafana for visualization, Prometheus for metrics collection, and Loki for log aggregation.

## Usage

To get started, make sure you have [Docker installed](https://docs.docker.com/docker-for-mac/install/) on your system, and then clone this repository.

From the project's root directory run `./vendor/bin/sail up`. If you want to run this in background, run `./vendor/bin/sail up -d`. Then open up your browser of choice to [http://localhost:80,](http://localhost:80) and you should see your Laravel app is running. 

We have added three more container that handle Composer, NPM, and Artisan commands without having to have these platforms installed on your local computer. Use the following command templates from your project root:

- `./vendor/bin/sail composer install`
- `./vendor/bin/sail npm run dev`
- `./vendor/bin/sail php artisan migrate:refresh --seed` 

The created containers and their respective ports (if used) are as follows:

- **Laravel Application: Exposes port** - `:8000`
- **MySQL Database: Exposes port** - `:3306`
- **Grafana: Exposes port** - `:3000`
- **Prometheus: Exposes port** - `:9000`
- **Loki: Exposes port** - `:3100`

These ports facilitate access to the Laravel application, Grafana dashboard, Prometheus metrics, and Loki logs.

## Persistent MySQL Storage

By default, whenever you down the docker-compose network, your MySQL data will be removed as the containers are destroyed. If you would like to have your data after bringing containers up, do the following:

1. Create a `mysql` folder in the project root, alongside the `nginx` and `src` folders.
2. Under the mysql service in your `docker-compose.yml` file, add the following lines:

```
volumes:
  - ./.docker/mysql:/var/lib/mysql
```

**Credit:** Following this repo [here](https://github.com/aschmelyun/laravel-grafana-dashboard).
