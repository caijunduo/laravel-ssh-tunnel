<?php

namespace Caijunduo\LaravelSSHTunnel;

use RuntimeException;
use Symfony\Component\Process\Process;

class SshTunnel
{
    protected $bins;

    protected $bin;

    protected $temporary = [];

    protected $tunnels = [];

    protected $database = [];

    /**
     * @var array{
     *     host: string,
     *     port: int,
     *     localPort: int,
     *     tunnel: array{
     *         host: string,
     *         user: string,
     *         port: int,
     *     }
     * }
     */
    protected $cached = [];

    public function __construct(string $bin, array $temporary, array $tunnels, array $database)
    {
        $this->bins = $bin;
        $this->temporary = $temporary;
        $this->tunnels = $tunnels;
        $this->database = $database;
    }

    protected function bin()
    {
        if (!$this->bin) {
            $bins = explode(':', $this->bins);
            foreach ($bins as $bin) {
                exec("which $bin", $output, $returnVar);
                if ($returnVar === 0 && !empty($output[0]) && file_exists($output[0])) {
                    $this->bin = $bin;
                    return $this->bin;
                }
            }
            throw  new RuntimeException('autossh not exists');
        }
        return $this->bin;
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

    protected function pidname(string $file): string
    {
        return $file . '.pid';
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

    protected function tunnelLine(array $tunnel): string
    {
        return sprintf('%s@%s -p %d', $tunnel['user'], $tunnel['host'], intval($tunnel['port']));
    }

    protected function connectionLine(string $host, int $port, int $localPort): string
    {
        return sprintf('%d:%s:%d', $localPort, $host, $port);
    }

    protected function commandPrefix(): string
    {
        return sprintf('%s -M 0 -f -C -N -L', $this->bin());
    }

    public function bootCached(): array
    {
        if (!$this->cached) {
            foreach ($this->database as $databaseKey => $connections) {
                foreach ($connections as $connection => $tunnel) {
                    [$host, $port] = $this->arguments($databaseKey, $connection);
                    if ($this->hasCached($host, $port)) {
                        $this->update($databaseKey, $connection, $this->cachedLocalPort($host, $port));
                    }
                }
            }
        }
        return $this->cached;
    }

    protected function hasCached(string $host, int $port): bool
    {
        $file = $this->directory() . $this->filename($host, $port);

        if (!isset($this->cached[$file])) {
            if (!file_exists($file) || !file_exists($this->pidname($file))) {
                @unlink($file);
                @unlink($this->pidname($file));
                return false;
            }
            $this->cached[$file] = json_decode(file_get_contents($file), true);
            $this->cached[$file]['pid'] = intval(file_get_contents($this->pidname($file)));
        }

        return true;
    }

    protected function cachedLocalPort(string $host, int $port): int
    {
        $file = $this->directory() . $this->filename($host, $port);
        return $this->cached[$file]['localPort'];
    }

    protected function connect(string $tunnelConnection, string $host, int $port): int
    {
        if ($this->hasCached($host, $port)) {
            return $this->cachedLocalPort($host, $port);
        }

        $file = $this->directory() . $this->filename($host, $port);

        if (!($localPort = $this->localPort())) {
            throw new RuntimeException("available port not found");
        }

        $commandPrefix = $this->commandPrefix();
        $connectionLine = $this->connectionLine($host, $port, $localPort);
        if (!($tunnel = $this->tunnels[$tunnelConnection])) {
            throw new RuntimeException("tunnel not exists: $tunnelConnection");
        }
        $tunnelLine = $this->tunnelLine($tunnel);
        $commandLine = "$commandPrefix $connectionLine $tunnelLine";

        $process = Process::fromShellCommandline($commandLine);
        $process->setEnv([
            'AUTOSSH_PIDFILE' => $file . '.pid',
        ]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput(), $process->getExitCode());
        }

        file_put_contents($file, json_encode(compact('host', 'port', 'localPort', 'tunnel'), JSON_UNESCAPED_UNICODE));

        return $localPort;
    }

    protected function run($databaseKey, $connection, $tunnel)
    {
        $this->update($databaseKey, $connection,
            $this->connect($tunnel, ...$this->arguments($databaseKey, $connection))
        );
    }

    protected function arguments($databaseKey, $connection): array
    {
        if (!($host = config("database.$databaseKey.$connection.host"))) {
            throw new RuntimeException("database.$databaseKey.$connection.host not found");
        }
        if (!($port = config("database.$databaseKey.$connection.port"))) {
            throw new RuntimeException("database.$databaseKey.$connection.port not found");
        }
        return [$host, $port];
    }

    protected function update($databaseKey, $connection, $port)
    {
        config(["database.$databaseKey.$connection.host" => '127.0.0.1']);
        config(["database.$databaseKey.$connection.port" => $port]);
    }

    public function start()
    {
        foreach ($this->database as $databaseKey => $connections) {
            foreach ($connections as $connection => $tunnel) {
                $this->run($databaseKey, $connection, $tunnel);
            }
        }
    }

    public function stop()
    {
        foreach ($this->list() as $file => $tunnel) {
            $pid = intval(file_get_contents($file.'.pid'));
            if ($pid) {
                $process = Process::fromShellCommandline("kill -9 $pid");
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new RuntimeException($process->getErrorOutput(), $process->getExitCode());
                }
            }
            file_exists($file) && unlink($file);
            file_exists($file.'.pid') && unlink($file.'.pid');
        }
    }

    public function list(): array
    {
        return $this->bootCached();
    }
}
