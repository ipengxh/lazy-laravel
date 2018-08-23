<?php
namespace LazyLaravel;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Created by Bruce Peng.
 * Date: 2018/8/14
 */

class CreateLazyControllerCommand extends GeneratorCommand
{
    protected $signature = "make:lazy {name}";

    protected $description = "Create a new lazy suite";

    protected function getOptions()
    {
        return [
            [
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Generate a resource controller for the given model'
            ]
        ];
    }
}