<?php

namespace Diana\Router\Tests\Controllers;

use Diana\Router\Attributes\Get;

class ParamController
{
    #[Get('/noparam')]
    public function noparam()
    {
        return 'noparam';
    }

    #[Get('/param/:param')]
    public function param($param)
    {
        return $param;
    }
}
