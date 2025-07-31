<?php

namespace Caijunduo\LaravelSshTunnel\Commands;

use Illuminate\Console\Command;

class SshTunnelListCommand extends Command
{
    protected $signature = 'ssh-tunnel:list';

    protected $description = 'Display SSH tunnel list';

    public function handle()
    {
        $rows = [];
        $list = app('ssh-tunnel')->list();
        foreach ($list as $file => $item) {
            $rows[] = [
                $item['pid'],
                "127.0.0.1:{$item['localPort']}",
                "{$item['host']}:{$item['port']}",
                "{$item['tunnel']['user']}@{$item['tunnel']['host']}:{$item['tunnel']['port']}",
                $file
            ];
        }
        if ($rows) {
            $headers = ['PID', 'Local','Remote', 'Tunnel', 'File'];
            $this->table($headers, $rows);
        }
    }
}
