<?php

namespace think\queue\command;

use think\helper\Str;
use think\console\Command;
use think\migration\Creator;

class Table extends Command
{
    public function handle()
    {
        if ( ! $this->app->has('migration.creator')) {
            $this->output->error('Install think-migration first please');

            return;
        }

        $table = $this->app->config->get('queue.connections.database.table');

        $className = Str::studly("create_{$table}_table");

        /** @var Creator $creator */
        $creator = $this->app->get('migration.creator');

        $path = $creator->create($className);

        // Load the alternative template if it is defined.
        $contents = file_get_contents(__DIR__ . '/stubs/jobs.stub');

        // inject the class names appropriate to this migration
        $contents = strtr($contents, [
            'CreateJobsTable' => $className,
            '{{table}}'       => $table,
        ]);

        file_put_contents($path, $contents);

        $this->output->info('Migration created successfully!');
    }

    protected function configure()
    {
        $this->setName('queue:table')
            ->setDescription('Create a migration for the queue jobs database table');
    }
}
