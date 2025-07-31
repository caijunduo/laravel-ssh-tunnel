<?php

namespace Caijunduo\LaravelSshTunnel\Commands;

use Illuminate\Console\Command;

class SshTunnelStopCommand extends Command
{
    protected $signature = 'ssh-tunnel:stop';

    protected $description = 'Command description';

    public function handle()
    {
        app('ssh-tunnel')->stop();
    }
}
