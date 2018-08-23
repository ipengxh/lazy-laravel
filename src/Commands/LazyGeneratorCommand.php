<?php

namespace LazyLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Created by Bruce Peng.
 * Date: 2018/8/23
 */

class LazyGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "make:lazy {name}
    {--table= : Force table name for lazy suite}
    {--without-model : Do not create model}
    {--without-requests : Do not create request validators}
    {--without-views : Do not create blade view files}
    {--model= : Generate lazy suite for the given model}
    {--view-path : Generate blade view files in the given path}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new lazy laravel suite';

    protected $name;

    public function handle()
    {
        $name = ucfirst($this->argument('name'));
        if (!$this->confirmBuild($name)) {
            // do nothing and quit
            $this->info("OK, fix it.");
            exit;
        }
    }

    /**
     * @return bool|string
     */
    protected function getTableName()
    {
        foreach ($this->guessTableNames() as $name) {
            try {
                \DB::statement("SHOW CREATE TABLE {$name};");
                return $name;
            } catch (QueryException $e) {
                // do nothing;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    protected function guessTableNames()
    {
        $names = [];
        $prefix = \DB::getTablePrefix();
        if ($table = $this->option('table')) {
        } else {
            $table = str_plural(lcfirst($this->argument('name')));
        }
        if ($prefix) {
            $names[] = $prefix . $table;
        } else {
            $names[] = $table;
        }
        return $names;
    }

    protected function getModelName()
    {

    }

    protected function getModelPath()
    {

    }

    protected function getViewPath()
    {

    }

    protected function confirmBuild($name)
    {
        $table = $this->getTableName();
        if (!$table) {
            $guessTableNames = implode(', ', $this->guessTableNames());
            $this->error("Table {$guessTableNames} is not exists, make sure you've created this table.");
            exit;
        }
        $this->info("You'll build a suite for {$name} using table `{$table}` with:");
        if (!$this->option('without-model')) {
            $this->info("\tmodel:\t\t" . app_path("Models/{$name}.php"));
        }
        $this->info("\tcontroller:\t" . app_path("Http/Controllers/{$name}Controller.php"));
        if (!$this->option('without-requests')) {
            $this->info("\trequests:\t" . app_path("Http/Requests/{$name}/StoreRequest.php"));
            $this->info("\t\t\t" . app_path("Http/Requests/{$name}/UpdateRequest.php"));
        }
        return $this->confirm("Are these correct?", true);
    }
}