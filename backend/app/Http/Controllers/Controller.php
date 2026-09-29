<?php

namespace App\Http\Controllers;

use App\Support\SchoolAccess;

abstract class Controller
{
    protected function access(): SchoolAccess
    {
        return app(SchoolAccess::class);
    }
}
