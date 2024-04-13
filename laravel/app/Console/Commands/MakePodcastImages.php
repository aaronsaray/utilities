<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Intervention\Image\ImageManager;
use Illuminate\Console\Command;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

class MakePodcastImages extends Command
{
    protected $signature = 'make:podcast-images';

    protected $description = 'Makes podcast images into the same directory as the file is from';

    protected ImageManager $imageManager;

    protected ImageInterface $podcastWaveform;

    public function handle(ImageManager $imageManager): int
    {
        $this->imageManager = $imageManager; // done like this so that it's not auto init from the constructor

        $podcastLocation = $this->ask('Where is the file located?');

        if (!is_readable($podcastLocation)) {
            throw new RuntimeException('Can not read the file at ' . $podcastLocation);
        }

        $this->createPodcastWaveform($podcastLocation);

        $this->youtube($podcastLocation);

        $this->info('Success!');

        return static::SUCCESS;
    }

    protected function createPodcastWaveform(string $podcastLocation): void
    {
        // you can do it here -

        $this->podcastWaveform = $this->imageManager->create(400, 200);
    }

    protected function youtube(string $podcastLocation): void
    {
        $youtubeThumbnailLocation = dirname($podcastLocation) . '/youtube.jpg';

        if (!is_writable(dirname($youtubeThumbnailLocation))) {
            throw new RuntimeException('Can not write to ' . $youtubeThumbnailLocation);
        }

        $imageManager = ImageManager::gd();

        $image = $imageManager->read(resource_path('templates/youtube-thumbnail.jpg'));

        $image->save($youtubeThumbnailLocation);

        $this->line("Wrote {$youtubeThumbnailLocation}");
    }
}
