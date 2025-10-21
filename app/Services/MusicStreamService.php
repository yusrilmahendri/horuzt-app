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
    /**
     * Stream music file with Range header support for better performance
     *
     * @param Setting $setting
     * @param Request $request
     * @return StreamedResponse|Response
     */
    public function streamMusic(Setting $setting, Request $request)
    {
        try {
            if (!$setting->musik) {
                return response()->json(['message' => 'No music file associated with this setting.'], 404);
            }

            $filePath = storage_path('app/' . $setting->musik);

            if (!file_exists($filePath)) {
                Log::warning('Music file not found', [
                    'setting_id' => $setting->id,
                    'file_path' => $setting->musik
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

        return [
            'file_name' => basename($filePath),
            'file_size' => filesize($filePath),
            'mime_type' => mime_content_type($filePath),
            'url' => Storage::url($setting->musik),
            'last_modified' => filemtime($filePath)
        ];
    }

    /**
     * Validate audio file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     */
    public function validateAudioFile($file): bool
    {
        // Daftar MIME types audio yang lebih lengkap
        $allowedMimeTypes = [
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mpeg',
            'audio/x-mp3',
            'audio/mpeg3',
            'audio/x-mpeg-3',
            'audio/mpg',
            'audio/x-mpg',
            'audio/x-mpegaudio',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/x-pn-wav',
            'audio/ogg',
            'audio/x-ogg',
            'application/ogg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/aac',
            'audio/aacp',
            'audio/3gpp',
            'audio/3gpp2',
            'audio/flac',
            'audio/x-flac',
            'audio/webm',
            'audio/wma',
            'audio/x-ms-wma'
        ];

        // Ekstensi file yang diizinkan sebagai fallback
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'wma', 'webm', 'opus'];

        $maxSize = 50 * 1024 * 1024; // 50MB

        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // Validasi: cek MIME type ATAU ekstensi file
        $validMimeType = in_array($mimeType, $allowedMimeTypes);
        $validExtension = in_array($extension, $allowedExtensions);
        $validSize = $file->getSize() <= $maxSize;

        return ($validMimeType || $validExtension) && $validSize;
    }
}
