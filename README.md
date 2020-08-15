# Laravel Grafana
A pretty simplified docker-compose workflow that sets up a LEMP network of containers for local Laravel development. Creating this project for practicing Grafana, Prometheus and Loki to monitor the performance of Laravel application.

## Usage

To get started, make sure you have [Docker installed](https://docs.docker.com/docker-for-mac/install/) on your system, and then clone this repository.

First add your entire Laravel project to the `src` folder, then open a terminal and from this cloned repository's root run `docker-compose up -d --build`. Open up your browser of choice to [http://localhost:8080](http://localhost:8080) and you should see your Laravel app running as intended. 

We have added three more container that handle Composer, NPM, and Artisan commands without having to have these platforms installed on your local computer. Use the following command templates from your project root:

- `docker-compose run --rm composer install`
- `docker-compose run --rm npm run dev`
- `docker-compose run --rm artisan migrate:refresh --seed` 

The created container and their ports (if used) are as follows:

- **nginx** - `:8080`
- **mysql** - `:3306`
- **php** - `:9000`
- **npm**
- **composer**
- **artisan**
- **grafana** - `:3000`

## Persistent MySQL Storage

By default, whenever you down the docker-compose network, your MySQL data will be removed as the containers are destroyed. If you would like to have your data after bringing containers up, do the following:

1. Create a `mysql` folder in the project root, alongside the `nginx` and `src` folders.
2. Under the mysql service in your `docker-compose.yml` file, add the following lines:

```
volumes:
  - ./mysql:/var/lib/mysql
```



**Credit:** Following this repo [here](https://github.com/aschmelyun/laravel-grafana-dashboard).
