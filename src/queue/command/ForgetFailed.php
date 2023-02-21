<?php

namespace think\queue\command;

use think\console\Command;
use think\console\input\Argument;

class ForgetFailed extends Command
{
    public function handle()
    {
        if ($this->app['queue.failer']->forget($this->input->getArgument('id'))) {
            $this->output->info('Failed job deleted successfully!');
        } else {
            $this->output->error('No failed job matches the given ID.');
        }
    }

    protected function configure()
    {
        $this->setName('queue:forget')
            ->addArgument('id', Argument::REQUIRED, 'The ID of the failed job')
            ->setDescription('Delete a failed queue job');
    }
}
