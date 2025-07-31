<?php

namespace Caijunduo\LaravelSSHTunnel;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class SshTunnelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ssh-tunnel.php' => config_path('ssh-tunnel.php'),
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
            return new SshTunnel($config['bin'], $config['temporary'], $config['tunnel']);
        });

        foreach ($config['database'] as $key => $connections) {
            foreach ($connections as $connection => $tunnel) {
                [$host, $port] = $this->loadRunArguments($key, $connection);
                $localPort = $this->app['ssh-tunnel']->run($host, $port, $tunnel);
                $this->updateConnection($key, $connection, $localPort);
            }
        }

    }

    protected function loadRunArguments($key, $connection): array
    {
        if (!($host = config("database.$key.$connection.host"))) {
            throw new RuntimeException("database.$key.$connection.host not found");
        }
        if (!($port = config("database.$key.$connection.port"))) {
            throw new RuntimeException("database.$key.$connection.port not found");
        }
        return [$host, $port];
    }

    protected function updateConnection($key, $connection, $port)
    {
        config("database.$key.$connection.host", '127.0.0.1');
        config("database.$key.$connection.port", $port);
    }
}
