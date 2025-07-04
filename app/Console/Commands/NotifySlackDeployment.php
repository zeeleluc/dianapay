<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\SlackNotifier;

class NotifySlackDeployment extends Command
{
    protected $signature = 'slack:notify-deploy';
    protected $description = 'Send a "Deployment done" message to Slack';

    public function handle(): void
    {
        SlackNotifier::info('🚀 Deployment done.');
    }
}
