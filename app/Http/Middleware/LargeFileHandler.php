<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LargeFileHandler
{
    public function handle(Request $request, Closure $next)
    {
        $settings = config('upload.php_settings', []);

        $desiredSettings = [
            'upload_max_filesize' => $settings['upload_max_filesize'] ?? '60M',
            'post_max_size' => $settings['post_max_size'] ?? '200M',
            'max_execution_time' => $settings['max_execution_time'] ?? 600,
            'memory_limit' => $settings['memory_limit'] ?? '512M',
        ];

        $configErrors = [];

        foreach ($desiredSettings as $key => $value) {
            $before = ini_get($key);
            $result = @ini_set($key, $value);

            if ($result === false) {
                $configErrors[] = "{$key} cannot be set via ini_set (server restriction)";
            }
        }

        if (!empty($configErrors) && $request->hasFile('photo') || $request->hasFile('musik') || $request->hasFile('cover_photo')) {
            Log::warning('Upload configuration warnings', [
                'errors' => $configErrors,
                'current_limits' => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'memory_limit' => ini_get('memory_limit'),
                ],
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }
}
