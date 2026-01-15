<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TourController extends Controller
{
    public function create()
    {
        $airports = include resource_path('helpers/airports.php');
        return view('admin.tours.create', compact('airports'));
    }
}