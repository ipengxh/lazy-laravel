<?php
namespace LazyLaravel;
use Illuminate\Support\ServiceProvider;
use LazyLaravel\Commands\LazyGeneratorCommand;

/**
 * Created by Bruce Peng.
 * Date: 2018/8/14
 */

class LazyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerCommand();
    }

    protected function registerCommand()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LazyGeneratorCommand::class
            ]);
        }
    }
}