<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\TelegramController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'env' => config('app.env'),
        'debug' => config('app.debug'),
        'php_version' => phpversion(),
        'laravel_version' => app()->version(),
        'message' => 'Welcome to Laravel!'
    ]);
});

Route::post('/deploy/webhook', function(Request $request) {
    $output = null;
    $resultCode = null;
    exec('/var/www/deploy.sh 2>&1', $output, $resultCode);

    return response()->json(['status' => $resultCode === 0 ? 'success' : 'error', 'output' => $output]);
});

Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
