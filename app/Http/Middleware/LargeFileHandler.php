<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LargeFileHandler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Set PHP configuration for large file uploads with fallback values
        $settings = config('upload.php_settings', []);

        ini_set('upload_max_filesize', $settings['upload_max_filesize'] ?? '6M');
        ini_set('post_max_size', $settings['post_max_size'] ?? '6M');
        ini_set('max_execution_time', $settings['max_execution_time'] ?? 300);
        ini_set('memory_limit', $settings['memory_limit'] ?? '256M');

        return $next($request);
    }
}
