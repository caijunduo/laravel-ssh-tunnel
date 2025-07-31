<?php

namespace Caijunduo\LaravelSSHTunnel;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SshTunnel
{
    protected $bin;

    protected $temporary = [];

    protected $tunnels = [];

    public function __construct(string $bin, array $temporary, array $tunnels)
    {
        $this->bin = $bin;
        $this->temporary = $temporary;
        $this->tunnels = $tunnels;
    }

    protected function directory(): string
    {
        return rtrim($this->temporary['directory'], '/') . '/';
    }

    protected function filename(string $host, int $port): string
    {
        return sprintf('%s-%s:%d',
            trim($this->temporary['prefix'], '/'),
            $host,
            $port,
        );
    }

    protected function getCachePort($file): int
    {
        $ext = '.port';
        if (!file_exists($file . $ext)) {
            return 0;
        }
        return intval(file_get_contents($file . $ext));
    }

    protected function setCachePort(string $file, int $localPort)
    {
        $ext = '.port';
        file_put_contents($file . $ext, $localPort);
    }

    protected function localPort(): int
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return 0;
        }

        if (socket_bind($socket, '127.0.0.1', 0) === false) {
            socket_close($socket);
            return 0;
        }

        if (socket_listen($socket, 1) === false) {
            socket_close($socket);
            return 0;
        }

        $address = null;
        $port = null;
        if (socket_getsockname($socket, $address, $port) === false) {
            socket_close($socket);
            return 0;
        }

        socket_close($socket);
        return intval($port);
    }

    protected function tunnelLine(string $tunnel): string
    {
        if (!($config = $this->tunnels[$tunnel])) {
            throw new RuntimeException("tunnel not exists: $tunnel");
        }
        return sprintf('%s@%s -p %d', $config['user'], $config['host'], intval($config['port']));
    }

    protected function connectionLine(string $host, int $port, int $localPort): string
    {
        return sprintf('%d:%s:%d', $localPort, $host, $port);
    }

    protected function commandPrefix(): string
    {
        return sprintf('%s -M 0 -f -C -N -L', $this->bin);
    }

    protected function getEnv(string $file): array
    {
        return [
            'AUTOSSH_PIDFILE' => $file . '.pid',
            'AUTOSSH_LOGFILE' => $file . '.log',
        ];
    }

    public function run(string $host, int $port, string $tunnel): int
    {
        $file = $this->directory() . $this->filename($host, $port);

        if ($localPort = $this->getCachePort($file)) {
            return $localPort;
        }

        if (!($localPort = $this->localPort())) {
            throw new RuntimeException("available port not found");
        }

        $commandPrefix = $this->commandPrefix();
        $connectionLine = $this->connectionLine($host, $port, $localPort);
        $tunnelLine = $this->tunnelLine($tunnel);
        $commandLine = "$commandPrefix $connectionLine $tunnelLine";

        exec($commandLine, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new  RuntimeException("command failed: $commandLine");
        }

        $this->setCachePort($file, $localPort);
        return $localPort;
    }
}
