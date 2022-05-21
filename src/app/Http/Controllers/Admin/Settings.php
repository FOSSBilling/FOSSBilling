<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class Settings extends Controller
{
    //
    public function show(Request $request)
    {
        $settings = Setting::all();
        return view('admin.settings.show',['settings'=>$settings]);
    }
    public function save(Request $request)
    {
        foreach ($request->settings as $key => $value) {
            $row = Setting::firstOrCreate(['key'=>$key], ['key'=>$key,'value'=>$value]);
            $row->value = $value;
            $row->save();
        }
        $settings = Setting::all();

        return view('admin.settings.show',['settings'=>$settings]);
    }
    
}
