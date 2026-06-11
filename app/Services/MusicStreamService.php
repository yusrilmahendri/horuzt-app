<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MusicStreamService
{
    protected MusicResolverService $resolver;

    public function __construct(MusicResolverService $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Stream the effective music for a setting with Range header support.
     *
     * Effective music is resolved with priority: custom upload > selected
     * catalog track > default track. This keeps existing endpoints unchanged
     * while supporting catalog/default playback.
     *
     * @param Setting $setting
     * @param Request $request
     * @return StreamedResponse|Response
     */
    public function streamMusic(Setting $setting, Request $request)
    {
        try {
            $resolved = $this->resolver->resolve($setting);

            if (!$resolved) {
                return response()->json(['message' => 'No music file associated with this setting.'], 404);
            }

            $filePath = $resolved['absolute_path'];

            if (!file_exists($filePath)) {
                Log::warning('Music file not found', [
                    'setting_id' => $setting->id,
                    'source' => $resolved['source'] ?? null,
                    'file_path' => $resolved['storage_path'] ?? null,
                ]);
                return response()->json(['message' => 'Music file not found.'], 404);
            }

            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath) ?: 'audio/mpeg';
            
            // Handle Range requests for streaming
            $range = $request->header('Range');
            
            if ($range) {
                return $this->handleRangeRequest($filePath, $fileSize, $mimeType, $range);
            }

            // Return full file if no range requested
            return $this->streamFullFile($filePath, $mimeType, $fileSize);

        } catch (\Exception $e) {
            Log::error('Music streaming failed', [
                'setting_id' => $setting->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to stream music file.'], 500);
        }
    }

    /**
     * Handle HTTP Range requests for chunked streaming
     *
     * @param string $filePath
     * @param int $fileSize
     * @param string $mimeType
     * @param string $range
     * @return StreamedResponse
     */
    private function handleRangeRequest(string $filePath, int $fileSize, string $mimeType, string $range): StreamedResponse
    {
        // Parse range header (e.g., "bytes=0-1023")
        preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
        
        $start = intval($matches[1]);
        $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
        
        // Ensure valid range
        if ($start > $end || $start >= $fileSize) {
            return response()->json(['message' => 'Invalid range request.'], 416);
        }
        
        $contentLength = $end - $start + 1;
        
        return response()->stream(function () use ($filePath, $start, $contentLength) {
            $handle = fopen($filePath, 'rb');
            fseek($handle, $start);
            
            $chunkSize = 8192; // 8KB chunks
            $bytesRead = 0;
            
            while ($bytesRead < $contentLength && !feof($handle)) {
                $remainingBytes = $contentLength - $bytesRead;
                $currentChunkSize = min($chunkSize, $remainingBytes);
                
                echo fread($handle, $currentChunkSize);
                $bytesRead += $currentChunkSize;
                
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            
            fclose($handle);
        }, 206, [
            'Content-Type' => $mimeType,
            'Content-Length' => $contentLength,
            'Content-Range' => "bytes $start-$end/$fileSize",
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'inline'
        ]);
    }

    /**
     * Stream the complete file
     *
     * @param string $filePath
     * @param string $mimeType
     * @param int $fileSize
     * @return StreamedResponse
     */
    private function streamFullFile(string $filePath, string $mimeType, int $fileSize): StreamedResponse
    {
        return response()->stream(function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            
            while (!feof($handle)) {
                echo fread($handle, 8192); // 8KB chunks
                
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            
            fclose($handle);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'inline'
        ]);
    }

    /**
     * Download music file
     *
     * @param Setting $setting
     * @return BinaryFileResponse|Response
     */
    public function downloadMusic(Setting $setting)
    {
        try {
            if (!$setting->musik) {
                return response()->json(['message' => 'No music file associated with this setting.'], 404);
            }

            $filePath = storage_path('app/' . $setting->musik);

            if (!file_exists($filePath)) {
                Log::warning('Music file not found for download', [
                    'setting_id' => $setting->id,
                    'file_path' => $setting->musik
                ]);
                return response()->json(['message' => 'Music file not found.'], 404);
            }

            return response()->download($filePath, basename($filePath), [
                'Content-Type' => mime_content_type($filePath) ?: 'audio/mpeg'
            ]);

        } catch (\Exception $e) {
            Log::error('Music download failed', [
                'setting_id' => $setting->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to download music file.'], 500);
        }
    }

    /**
     * Delete music file from storage
     *
     * @param Setting $setting
     * @return bool
     */
    public function deleteMusic(Setting $setting): bool
    {
        try {
            if (!$setting->musik) {
                return true; // Already no music file
            }

            // Delete from storage
            $deleted = Storage::delete($setting->musik);
            
            if ($deleted) {
                Log::info('Music file deleted successfully', [
                    'setting_id' => $setting->id,
                    'file_path' => $setting->musik
                ]);
            }

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Music deletion failed', [
                'setting_id' => $setting->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get music file information
     *
     * @param Setting $setting
     * @return array|null
     */
    public function getMusicInfo(Setting $setting): ?array
    {
        if (!$setting->musik) {
            return null;
        }

        $filePath = storage_path('app/' . $setting->musik);

        if (!file_exists($filePath)) {
            return null;
        }

        // Normalize stored path so the public URL never becomes /storage/public/music/...
        // DB may store "public/music/file.mp3"; public URL must be /storage/music/file.mp3
        $publicPath = preg_replace('#^public/#', '', $setting->musik);

        return [
            'file_name' => basename($filePath),
            'file_size' => filesize($filePath),
            'mime_type' => mime_content_type($filePath),
            'url' => asset('storage/' . $publicPath),
            'last_modified' => filemtime($filePath)
        ];
    }

    /**
     * Get the effective music info (custom / catalog / default) for a setting.
     * Delegates to the resolver so the priority logic lives in one place.
     *
     * @param Setting $setting
     * @return array<string,mixed>|null
     */
    public function getEffectiveMusicInfo(Setting $setting): ?array
    {
        return $this->resolver->resolveInfo($setting);
    }

    /**
     * Validate audio file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     */
    public function validateAudioFile($file): bool
    {
        $allowedMimeTypes = [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/m4a',
        ];
        $maxSize = 10 * 1024 * 1024; // 10MB

        return in_array($file->getMimeType(), $allowedMimeTypes) && 
               $file->getSize() <= $maxSize;
    }
}