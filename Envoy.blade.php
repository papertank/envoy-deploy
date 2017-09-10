@setup
	require __DIR__.'/vendor/autoload.php';
	$dotenv = new Dotenv\Dotenv(__DIR__);
	try {
		$dotenv->load();
		$dotenv->required(['DEPLOY_SERVER', 'DEPLOY_REPOSITORY', 'DEPLOY_PATH'])->notEmpty();
	} catch ( Exception $e )  {
		echo $e->getMessage();
	}

	$server = getenv('DEPLOY_SERVER');
	$repo = getenv('DEPLOY_REPOSITORY');
	$path = getenv('DEPLOY_PATH');
	$slack = getenv('DEPLOY_SLACK_WEBHOOK');

	if ( substr($path, 0, 1) !== '/' ) throw new Exception('Careful - your deployment path does not begin with /');

	$date = ( new DateTime )->format('YmdHis');
	$env = isset($env) ? $env : "production";
	$branch = isset($branch) ? $branch : "master";
	$path = rtrim($path, '/');
	$release = $path.'/'.$date;
@endsetup

@servers(['web' => $server])

@task('init')
	if [ ! -d {{ $path }}/current ]; then
		cd {{ $path }}
		git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
		echo "Repository cloned"
		mv {{ $release }}/storage {{ $path }}/storage
		ln -s {{ $path }}/storage {{ $release }}/storage
		ln -s {{ $path }}/storage/public {{ $release }}/public/storage
		echo "Storage directory set up"
		cp {{ $release }}/.env.example {{ $path }}/.env
		ln -s {{ $path }}/.env {{ $release }}/.env
		echo "Environment file set up"
		rm -rf {{ $release }}
		echo "Deployment path initialised. Run 'envoy run deploy' now."
	else
		echo "Deployment path already initialised (current symlink exists)!"
	fi
@endtask

@story('deploy')
	deployment_start
	deployment_links
	deployment_composer
	deployment_migrate
	deployment_cache
	deployment_optimize
	deployment_finish
	deployment_option_cleanup
@endstory

@story('deploy_cleanup')
	deployment_start
	deployment_links
	deployment_composer
	deployment_migrate
	deployment_cache
	deployment_optimize
	deployment_finish
	deployment_cleanup
@endstory

@task('deployment_start')
	cd {{ $path }}
	echo "Deployment ({{ $date }}) started"
	git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
	echo "Repository cloned"
@endtask

@task('deployment_links')
	cd {{ $path }}
	rm -rf {{ $release }}/storage
	ln -s {{ $path }}/storage {{ $release }}/storage
	ln -s {{ $path }}/storage/public {{ $release }}/public/storage
	echo "Storage directories set up"
	ln -s {{ $path }}/.env {{ $release }}/.env
	echo "Environment file set up"
@endtask

@task('deployment_composer')
	cd {{ $release }}
	composer install --no-interaction --quiet --no-dev
@endtask

@task('deployment_migrate')
	php {{ $release }}/artisan migrate --env={{ $env }} --force --no-interaction
@endtask

@task('deployment_cache')
	php {{ $release }}/artisan view:clear --quiet
	php {{ $release }}/artisan cache:clear --quiet
	php {{ $release }}/artisan config:cache --quiet
	echo 'Cache cleared'
@endtask

@task('deployment_optimize')
	php {{ $release }}/artisan optimize --quiet
@endtask

@task('deployment_finish')
	ln -nfs {{ $release }} {{ $path }}/current
	echo "Deployment ({{ $date }}) finished"
@endtask

@task('deployment_cleanup')
	cd {{ $path }}
	find . -maxdepth 1 -name "20*" -mmin +2880 | head -n 5 | xargs rm -Rf
	echo "Cleaned up old deployments"
@endtask

@task('deployment_option_cleanup')
	cd {{ $path }}
	@if ( isset($cleanup) && $cleanup )
	find . -maxdepth 1 -name "20*" -mmin +2880 | head -n 5 | xargs rm -Rf
	echo "Cleaned up old deployments"
	@endif
@endtask

{{--
@after
	@slack($slack, '#deployments', "Deployment on {$server}: {$date} complete")
@endafter
--}}
