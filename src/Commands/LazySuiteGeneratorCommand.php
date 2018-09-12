<?php

namespace LazyLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use DB;

class LazySuiteGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "lazy:suite {name}
    {--t|table= : Force table name for lazy suite}
    {--m|model= : Generate lazy suite for the given model}
    {--without-model : Do not create model}
    {--without-requests : Do not create request validators}
    {--without-views : Do not create blade view files}
    {--view-path : Generate blade view files in the given path}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new lazy laravel suite';

    protected $originalName;

    const NAME_SNAKE = 1;
    const NAME_CAMEL = 2;
    const NAME_UC_FIRST = 4;
    const NAME_LC_FIRST = 8;
    const NAME_WORDS = 16;

    public function handle()
    {
        if (!$this->confirmBuild()) {
            // do nothing and quit
            $this->warn("OK.");
            exit;
        }
    }

    /**
     * @param int $option
     * @return string|array
     */
    protected function parseName($option = 0)
    {
        $name = $this->argument('name');
        if ($option & self::NAME_SNAKE) {
            $name = snake_case($name);
        }

        if ($option & self::NAME_CAMEL) {
            $name = camel_case($name);
        }

        if ($option & self::NAME_UC_FIRST) {
            $name = ucfirst($name);
        }

        if ($option & self::NAME_LC_FIRST) {
            $name = lcfirst($name);
        }

        if ($option & self::NAME_WORDS) {
            $words = explode('_', $this->parseName(self::NAME_SNAKE));
            foreach ($words as &$word) {
                if ($option & self::NAME_UC_FIRST) {
                    $word = ucfirst($word);
                } elseif ($option & self::NAME_LC_FIRST) {
                    $word = lcfirst($word);
                }
            }
            return $words;
        }
        return $name;
    }

    /**
     * @return bool|string
     */
    protected function getTableName()
    {
        $this->originalName = $this->argument('name');
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
        $prefix = \DB::getTablePrefix();
        if ($table = $this->option('table')) {
        } else {
            $table = str_plural(lcfirst($this->argument('name')));
        }
        return [$prefix ? $prefix . $table : $table];
    }

    protected function getModelName()
    {
        if ($this->option('without-model')) {
            return null;
        }
        if ($this->option('model')) {
            return $this->option('model');
        }
        return ucfirst(camel_case(str_singular($this->argument('name'))));
    }

    protected function getModelPath()
    {
        return app_path(config('lazy.model_path') . '/' . $this->getModelName() . '.php');
    }

    protected function getViewPath()
    {
        return resource_path(str_singular(str_replace("_", "/", snake_case($this->name))));
    }

    protected function getViewNames()
    {
        return [
            'index' => $this->getViewPath() . "/index.blade.php",
            'show' => $this->getViewPath() . "/show.blade.php",
            'create' => $this->getViewPath() . "/create.blade.php",
            'edit' => $this->getViewPath() . "/edit.blade.php",
        ];
    }

    protected function getControllerName()
    {
        return $this->getModelName() . 'Controller';
    }

    protected function getControllerPath()
    {
        return app_path("app/Http/Controllers/" . $this->getControllerName() . '.php');
    }

    protected function getRequestPath()
    {
        $name = $this->parseName();
        $words = explode('_', $name);
        foreach ($words as &$path) {
            $path = ucfirst($path);
        }
        return app_path("app/Http/Requests/" . implode('/', $words));
    }

    protected function getRequestNames()
    {
        return [
            'store' => $this->getRequestPath() . "/StoreRequest.php",
            'update' => $this->getRequestPath() . "/UpdateRequest.php",
        ];
    }

    protected function getModelRelations()
    {
        $db = env('DB_DATABASE');
        $tables = DB::select("SHOW TABLES;");
        $key = "Tables_in_" . $db;
        foreach ($tables as $table) {
            if ('migrations' == $table->$key) {
                continue;
            }
            $columns = Schema::getColumnListing($table->$key);
            foreach ($columns as $column) {

            }
        }
    }

    protected function confirmBuild()
    {
        $name = $this->parseName();
        $table = $this->getTableName();
        if (!$table) {
            $guessTableNames = implode(', ', $this->guessTableNames());
            $this->error("Table {$guessTableNames} is not exists, make sure you've created this table.");
            exit;
        }
        $this->info("You'll build a suite for {$name} using table `{$table}` with:");
        if ($model = $this->getModelName()) {
            $this->info("\tmodel:\t\t" . $this->getModelPath());
            $this->info("\tmodel relations:\t" . $this->getModelRelations());
            $this->info("\tcontroller:\t" . $this->getControllerPath());
        }
        if (!$this->option('without-requests')) {
            $this->info("\trequests:");
            foreach ($this->getRequestNames() as $name) {
                $this->info("\t\t\t" . $name);
            }
        }
        if (!$this->option('without-views')) {
            $this->info("\tresources:");
            foreach ($this->getViewNames() as $name) {
                $this->info("\t\t\t{$name}");
            }
        }
        return $this->confirm("Confirm?", true);
    }
}