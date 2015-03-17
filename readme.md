## Laravel Envoy Deploy

This repository includes an Envoy.blade.php script that is designed to provide a very basic "zero-downtime" deployment option using the open-source [Laravel Envoy](http://laravel.com/docs/5.0/envoy) tool.

## Requirements

This Envoy script is designed to be used with Laravel 5 projects.

## Installation

Your must have Envoy installed using the Composer global command:

	composer global require "laravel/envoy=~1.0"

## Usage

### Setup

Download or clone this repository then edit the envoy.config.php file with the Git repository and server path for your app.

The `$path` (server path) should already be created in your server and be a blank directory.

Set the `@servers` ssh login command in Envoy.blade.php to your server's username, host name and optional port.

You should set your website root directory (in vhost / server config) to `$path`/current (e.g /var/www/default/current)

### Init

When you're happy with the config, run the init task on your local machine by running the following in the repository directory

	envoy run init

You can specify the Laravel environment (for artisan:migrate command) and git branch as options

	envoy run init --branch=develop --env=development

You only need to run the init task once.

The init task creates a `.env` file in your root path - make sure and update the environment variables appropriately.

### Deploy

Each time you want to deploy


## How it Works

Your `$path` directory will look something like this after you init and then deploy.

	20150317110501/
	20150317114500/
	current -> ./20150317114500
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
	
## Todo

 * Cleanup old deployment folders

## Disclaimer

This has only been tested so far with a Laravel Homestead / Vagrant VM. Use on a live server at your own risk and make sure you read through the script and set the config correctly!

## Contributing

Please submit improvements and fixes :)

## Author

[Papertank Limited](http://papertank.co.uk)
