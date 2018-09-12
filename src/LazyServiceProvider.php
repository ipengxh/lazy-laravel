<?php
namespace LazyLaravel;
use Illuminate\Support\ServiceProvider;
use LazyLaravel\Commands\LazyModelsGeneratorCommand;
use LazyLaravel\Commands\LazySuiteGeneratorCommand;

/**
 * Created by Bruce Peng.
 * Date: 2018/8/14
 */

class LazyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerCommand();
        $this->registerVendorPublisher();
    }

    protected function registerCommand()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LazySuiteGeneratorCommand::class,
                LazyModelsGeneratorCommand::class
            ]);
        }
    }

    protected function registerVendorPublisher()
    {
        $this->publishes([
            __DIR__.'/config' => config_path(),
        ]);
    }
}