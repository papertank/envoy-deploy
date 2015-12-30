@include('envoy.config.php');

@servers(['web' => $ssh])

@setup
	if ( ! isset($ssh) ) {
		throw new Exception('SSH login username/host is not set');
	}


	if ( ! isset($repo) ) {
		throw new Exception('Git repository is not set');
	}

	if ( ! isset($path) ) {
		throw new Exception('Path is not set');
	}

	if ( substr($path, 0, 1) !== '/' ) {
		throw new Exception('Careful - your path does not begin with /');
	}

	$now = new DateTime();
	$date = $now->format('YmdHis');
	$env = isset($env) ? $env : "production";
	$branch = isset($branch) ? $branch : "master";
	$path = rtrim($path, '/');
	$release = $path.'/'.$date;
@endsetup

@task('init')
	cd {{ $path }};
	git clone {{ $repo }} --branch={{ $branch }} --depth=1 {{ $release }};
	echo "Repository cloned";
	mv {{ $release }}/storage {{ $path }}/storage;
	ln -s {{ $path }}/storage {{ $release }}/storage;
	echo "Storage directory set up";
	cp {{ $release }}/.env.example {{ $path }}/.env;
	ln -s {{ $path }}/.env {{ $release }}/.env;
	echo "Environment file set up";
	cd {{ $release }};
	composer install --no-interaction;
	php artisan migrate --env={{ $env }} --force --no-interaction;
	ln -s {{ $release }} {{ $path }}/current;
	echo "Initial deployment ({{ $date }}) complete";
@endtask

@macro('deploy_cleanup')
    deploy
    cleanup
@endmacro

@task('deploy')
	cd {{ $path }};
	git clone {{ $repo }} --branch={{ $branch }} --depth=1 {{ $release }};
	echo "Repository cloned";
	rm -rf {{ $release }}/storage;
	ln -s {{ $path }}/storage {{ $release }}/storage;
	echo "Storage directory set up";
	ln -s {{ $path }}/.env {{ $release }}/.env;
	echo "Environment file set up";
	cd {{ $release }};
	composer install --no-interaction;
	php artisan migrate --env={{ $env }} --force --no-interaction;
	rm {{ $path }}/current;
	ln -s {{ $release }} {{ $path }}/current;
	echo "Deployment ({{ $date }}) complete";
@endtask

@task('cleanup')
	cd {{ $path }};
	find . -maxdepth 1 -name "20*" -mmin +2880 | head -n 5 | xargs rm -Rf;
	echo "Cleaned up old deployments";
@endtask
