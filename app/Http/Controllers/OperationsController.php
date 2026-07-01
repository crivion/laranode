<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Inertia\Inertia;

class OperationsController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Operations/Index', [
            'operations' => Operation::with('user:id,username')->latest()->paginate(30),
        ]);
    }
}
