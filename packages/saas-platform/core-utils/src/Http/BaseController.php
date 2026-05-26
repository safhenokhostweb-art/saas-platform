<?php

namespace SaaSPlatform\CoreUtils\Http;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

/**
 * Base controller for all package module controllers.
 * Replaces App\Http\Controllers\Controller so packages do not
 * need to depend on the host application's controller base class.
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
