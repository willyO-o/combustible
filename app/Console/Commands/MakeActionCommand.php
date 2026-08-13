<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('make:action {name}')]
#[Description('Crea una nueva clase Action en app/Actions')]
class MakeActionCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = str_replace('.php', '', $this->argument('name'));
        $name = str_replace('/', '\\', $name);

        $relativePath = str_replace('\\', '/', $name) . '.php';
        $path = app_path('Actions/' . $relativePath);

        if (file_exists($path)) {
            $this->error('El Action ya existe: ' . $path);
            return self::FAILURE;
        }

        $namespace = 'App\\Actions';
        $class = class_basename($name);

        if (str_contains($name, '\\')) {
            $subNamespace = str_replace('\\' . $class, '', $name);
            $namespace .= '\\' . $subNamespace;
        }

        $stub = <<<PHP
<?php

namespace {$namespace};

class {$class}
{
    public function execute(array \$datos)
    {
        //
    }
}

PHP;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);

        $this->info('Action creado: ' . $path);

        return self::SUCCESS;
    }
}
