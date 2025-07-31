<?php

namespace Caijunduo\LaravelSSHTunnel;

use Caijunduo\LaravelSshTunnel\Commands\SshTunnelListCommand;
use Caijunduo\LaravelSshTunnel\Commands\SshTunnelStartCommand;
use Caijunduo\LaravelSshTunnel\Commands\SshTunnelStopCommand;
use Illuminate\Support\ServiceProvider;

class SshTunnelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ssh-tunnel.php' => config_path('ssh-tunnel.php'),
            ]);
            $this->commands([
                SshTunnelListCommand::class,
                SshTunnelStartCommand::class,
                SshTunnelStopCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->registerSshTunnel();
    }

    protected function registerSshTunnel()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }
        if (!($config = config('ssh-tunnel', []))) {
            return;
        }
        if (!($config['enabled'] ?? false)) {
            return;
        }
        $this->app->singleton('ssh-tunnel', function () use ($config) {
            return new SshTunnel($config['bin'], $config['temporary'], $config['tunnel'], $config['database']);
        });
    }
}
