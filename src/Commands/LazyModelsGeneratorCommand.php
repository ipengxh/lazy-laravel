<?php

namespace LazyLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use DB;

class LazyModelsGeneratorCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "lazy:models 
    {--m|model : Just create one(some) model(s), use \",\" to specify two or more models}
    {--without-model : Do not create model(s), use \",\" to specify two or more models}
    {--p|path : Generate model files in the given path}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create model(s)';

    public function handle()
    {

    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . "/Stubs/model.stub";
    }
}