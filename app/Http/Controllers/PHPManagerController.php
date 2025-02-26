<?php

namespace App\Http\Controllers;

use App\Models\PhpVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PHPManagerController extends Controller
{

    public function getVersions(): JsonResponse
    {
        $versions = PhpVersion::active()->get();

        return response()->json($versions);
    }
}
