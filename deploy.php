<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/npm.php';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

set('application', 'game-hub');
set('repository', 'git@github.com:HAJDARODAVID/game-hub-v2.git');
set('branch', 'master');
set('keep_releases', 5);
set('ssh_multiplexing', false);

// Files/dirs kept between releases (the Laravel recipe already shares .env and storage).
add('shared_files', []);
add('shared_dirs', []);

// Deployer sets permissions with setfacl (the acl package on the server).
set('writable_mode', 'acl');
set('http_user', 'www-data');

// ---------------------------------------------------------------------------
// Hosts
// ---------------------------------------------------------------------------

host('production')
    ->setHostname('209.38.113.108')        // <- your droplet IP or domain
    ->setRemoteUser('deployer')
    ->setDeployPath('/var/www/game-hub-v2')
    ->set('php_fpm_service', 'php8.4-fpm');

// ---------------------------------------------------------------------------
// Tasks
// ---------------------------------------------------------------------------

desc('Build front-end assets with Vite');
task('npm:build', function () {
    // VITE_REVERB_* values come from shared/.env, which is already linked here.
    run('cd {{release_path}} && npm run build');
});

desc('Reload PHP-FPM so OPcache picks up the new release');
task('php-fpm:reload', function () {
    run('sudo systemctl reload {{php_fpm_service}}');
});

desc('Tell Reverb to restart (Supervisor brings it back up on the new release)');
task('artisan:reverb:restart', artisan('reverb:restart'));

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

// Install node deps and build assets after composer, before the release goes live.
after('deploy:vendors', 'npm:install');
after('npm:install', 'npm:build');

// Once the new release is live: reload FPM, restart queue worker and Reverb.
after('deploy:symlink', 'php-fpm:reload');
after('php-fpm:reload', 'artisan:queue:restart');
after('artisan:queue:restart', 'artisan:reverb:restart');

after('deploy:failed', 'deploy:unlock');
