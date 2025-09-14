<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateSampleImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:create-sample-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create sample placeholder images for themes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating sample theme images...');

        // Create simple placeholder images using imagecreate
        for ($i = 1; $i <= 3; $i++) {
            // Create preview image (800x600)
            $preview = imagecreate(800, 600);
            $bg_color = imagecolorallocate($preview, 100 + ($i * 30), 150 + ($i * 20), 200 + ($i * 10));
            $text_color = imagecolorallocate($preview, 255, 255, 255);

            imagestring($preview, 5, 300, 280, "Theme Preview $i", $text_color);
            imagestring($preview, 3, 320, 320, "800x600 Resolution", $text_color);

            $previewPath = storage_path('app/public/theme-images/previews/sample-preview-' . $i . '.jpg');
            imagejpeg($preview, $previewPath, 90);
            imagedestroy($preview);

            // Create thumbnail image (300x200)
            $thumbnail = imagecreate(300, 200);
            $bg_color = imagecolorallocate($thumbnail, 100 + ($i * 30), 150 + ($i * 20), 200 + ($i * 10));
            $text_color = imagecolorallocate($thumbnail, 255, 255, 255);

            imagestring($thumbnail, 4, 80, 80, "Theme $i", $text_color);
            imagestring($thumbnail, 2, 100, 110, "Thumbnail", $text_color);

            $thumbnailPath = storage_path('app/public/theme-images/thumbnails/sample-thumb-' . $i . '.jpg');
            imagejpeg($thumbnail, $thumbnailPath, 85);
            imagedestroy($thumbnail);

            $this->info("Created sample-preview-$i.jpg and sample-thumb-$i.jpg");
        }

        $this->info('Sample images created successfully!');

        return 0;
    }
}
