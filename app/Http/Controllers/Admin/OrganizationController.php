<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Base\BaseController;

class OrganizationController extends BaseController
{

    public function expired()
    {
        return view('layouts.backend_app');
    }
}
