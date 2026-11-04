<?php

namespace Diana\Controllers;

use Diana\Framework\Core\Application;
use Diana\IO\Response;
use Diana\Router\Attributes\Command;

class StubCommandsController
{
    #[Command("create-package", "name")]
    public function makeController(Application $app, string $name): ?Response
    {
        // TODO: Outsource
        $stub = str_replace(
            '{{name}}',
            $name,
            file_get_contents($app->path('stubs/Package.php.stub')) // todo: fix, this should be frameworkPath
        );
        $destination = $app->path('src/' . $name . '.php');
        if (file_exists($destination)) {
            return new Response("The given file [{$destination}] already exists.", 2);
        }
        file_put_contents($destination, $stub);

        return null;
    }
}
