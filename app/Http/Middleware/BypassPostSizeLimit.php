<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BypassPostSizeLimit
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
        // Bypass POST size limits for file upload endpoints with fallback values
        $settings = config('upload.php_settings', []);

        ini_set('post_max_size', '0'); // No limit for POST size
        ini_set('upload_max_filesize', $settings['upload_max_filesize'] ?? '6M');
        ini_set('max_file_uploads', $settings['max_file_uploads'] ?? 20);

        return $next($request);
    }
}
