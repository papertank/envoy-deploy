# Laravel Envoy Deploy

This repository includes an Envoy.blade.php script that is designed to provide a basic "zero-downtime" deployment option using the open-source [Laravel Envoy](http://laravel.com/docs/8.x/envoy) tool.

## Requirements

This Envoy script is designed to be used with Laravel 7+ projects and can be used within the Laravel root, or downloaded separately and included in your Laravel project.

## Installation

Your must have Envoy installed using the Composer global command:

	composer global require "laravel/envoy"

### Standalone

To download and run out-with your Laravel project, clone this directory and do a composer install.

### Laravel

#### Laravel 7+

To use within an existing Laravel 7+ project, you simply need to download the version 4 `Envoy.blade.php` file to your project root:

```
wget https://raw.githubusercontent.com/papertank/envoy-deploy/master/Envoy.blade.php
```

#### Laravel 5-6

To use within an existing Laravel 5 or 6 project, you simply need to download the version 2 `Envoy.blade.php` file to your project root:

```
wget https://raw.githubusercontent.com/papertank/envoy-deploy/v2/Envoy.blade.php
```

## Setup

### Config

Envoy Deploy uses [DotEnv](https://github.com/vlucas/phpdotenv) to fetch your server and repository details. If you are installing within a Laravel project, there will already be a `.env` file in the root, otherwise simply create one.

The following configuration items are required:

  - `DEPLOY_SERVER`
  - `DEPLOY_REPOSITORY`
  - `DEPLOY_PATH`

For example, deploying the standard Laravel repository on a Forge server, we might use:

```
DEPLOY_SERVER=forge@example.com
DEPLOY_REPOSITORY=https://github.com/laravel/laravel.git
DEPLOY_PATH=/home/forge/example.com
DEPLOY_HEALTH_CHECK=https://example.forge.com
```

The `DEPLOY_PATH` (server path) should already be created in your server and must be a blank directory.

### Host

Envoy Deploy uses symlinks to ensure that your server is always running from the latest deployment. As such you need to setup your Apache or Nginx host to point towards the `host/current/public` directory rather than simply `host/public`.

For example:

```
server {
    listen 80;
    server_name example.com;
    root /home/forge/example.com/current/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

## Usage

### Init

When you're happy with the config, run the init task on your local machine by running the following.

	envoy run init

You only need to run the init task once.

The init task creates a `.env` file in your root path (from your `.env.example` file), so make sure and update the environment variables appropriately. Once you have run the init task, you should proceed to run the deploy task (below).

### Deploy

Each time you want to deploy simply run the deploy task on your local machine in the repository direcory

	envoy run deploy

You can specify the Laravel environment (e.g. for artisan:migrate command) and git branch as options

	envoy run deploy --branch=develop --env=development

### Deploy with Cleanup

If you could like to deploy your repository and cleanup any old deployments at the same time, you can run

	envoy run deploy --cleanup

This will run the deploy script and then delete any old deployments older than 48 hours, limiting the number deleted to 5.

You can also run the cleanup script independently (without deploying) using

	envoy run deployment_cleanup

### Health Check

If you would like to perform a health check (for 200 response) after deploying, simply add your site's URL in the .env file:

```
DEPLOY_HEALTH_CHECK=https://example.forge.com
```

### Rollback

To rollback to the previous deployment (e.g. when a health check fails), you can simply run 

	envoy run rollback

## Commands

#### `envoy run init`

Initialise server for deployments

    Options
        --env=ENVIRONMENT        The environment to use. (Default: "production")
        --branch=BRANCH          The git branch to use. (Default: "master")

#### `envoy run deploy`

Run new deployment

    Options
        --env=ENVIRONMENT        The environment to use. (Default: "production")
        --branch=BRANCH          The git branch to use. (Default: "master")
        --cleanup                Whether to cleanup old deployments

#### `envoy run deployment_cleanup`

Delete any old deployments, leaving just the last 4.

#### `envoy run rollback`

In case the health check does not work, you can rollback and it will use the previous deploy.

## How it Works

Your `$path` directory will look something like this after you init and then deploy.

	releases/
	current -> ./releases/20220103125914
	storage/
	.env

As you can see, the *current* directory is symlinked to the latest deployment folder

Inside one of your deployment folders looks like the following (excluded some laravel folders for space)

	app/
	artisan
	boostrap/
	composer.json
	.env -> ../.env
	storage -> ../storage
	vendor/

The deployment folder .env file and storage directory are symlinked to the parent folders in the main (parent) path.

## Optional Features

### Laravel Horizon

If you use [Laravel Horizon](https://laravel.com/docs/8.x/horizon) for your Redis queue management, you should update the deployment script to restart queues using Horizon.

Replace:

```
php {{ $release }}/artisan queue:restart --quiet
```

With:

```
php {{ $release }}/artisan horizon:terminate
```

### Reload PHP FPM

If you use something like OPCache, you should reload the PHP FPM service at the end of each deployment.

Simply add the following to the end of the `deployment_finish` task. Note: you will need to change based on your PHP version and/or server setup.

```
sudo -S service php7.4-fpm reload
```

### Laravel Mix / NPM

If you use Laravel mix / npm dependencies in your project, you should add the (disabled by default) `deployment_npm` task to the deploy story. For example:

```
@story('deploy')
	deployment_start
	deployment_links
	deployment_composer
    deployment_npm
	deployment_migrate
	deployment_cache
	deployment_finish
	health_check
	deployment_option_cleanup
@endstory
```

If you only use Laravel mix for asset compilation and don't use any node scripts after deployment, you can update your deployment script to remove the node_modules folder and save some disk space on old deployments:

```
@task('deployment_npm')
	echo "Installing npm dependencies..."
	cd {{ $release }}
	npm install --no-audit --no-fund --no-optional
	echo "Running npm..."
	npm run {{ $env }} --silent
    rm -rf {{ $release }}/node_modules
@endtask
```

## Disclaimer

Before using on live server, it is best to test on a local VM (like [Laravel Homestead](https://laravel.com/docs/8.x/homestead)) first.

## Changes
V4.0
- Added optional deployment_npm task (disabled by default).
- Tidied up deployments into releases folder.
- Removed deploy_cleanup story to simplify - use 'envoy run deploy --cleanup'.
- Removed storage/public link and replaced with 'php artisan storage:link' command.

V3.0
- Updated DotEnv to "^4.0" for Laravel 7 compatibility.

V2.2
- Added `health_check` task and config.
- Added `rollback` task to update current to previous deployment.
- Updated `cleanup` to delete old deploys and leave last 4.
- Removed `deployment_optimize` task (no longer needed for Laravel 5 projects).
- Updated `deployment_composer` to use `--prefer-dist --optimize-autoloader` options.

V2.1 - Updated init to only initialize (rather than deploy).

v2.0 - Switched to using DotEnv (removing `envoy.config.php`) and cleaned up tasks/stories.

v1.0.1 - Added `cleanup` task and `deploy_cleanup` macro after changing cleanup command.

## Contributing

Please submit improvements and fixes :)

## Credits

 * [Servers for Hackers](https://serversforhackers.com/video/enhancing-envoy-deployment) for inspiration
 * [@noeldiaz on Laracasts](https://laracasts.com/@noeldiaz) for deployment cleanups idea
 * [Harmen Stoppels](https://serversforhackers.com/video/enhancing-envoy-deployment#comment-1900893160) for cloning HEAD only
 * [Jordi Puigdellívol @badchoice](https://github.com/BadChoice) for V2.2 updates (health check and rollback).


## Author

[Papertank Limited](http://papertank.co.uk)
