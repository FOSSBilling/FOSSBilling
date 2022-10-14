<?php

namespace App\View\Components;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\Component;
use Illuminate\View\View;

class AdminLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     *
     * @return Application|Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('layouts.admin.app');
    }
}
