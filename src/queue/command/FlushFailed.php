<?php

namespace think\queue\command;

use think\console\Command;

class FlushFailed extends Command
{
    public function handle()
    {
        $this->app->get('queue.failer')->flush();

        $this->output->info('All failed jobs deleted successfully!');
    }

    protected function configure()
    {
        $this->setName('queue:flush')
            ->setDescription('Flush all of the failed queue jobs');
    }
}
