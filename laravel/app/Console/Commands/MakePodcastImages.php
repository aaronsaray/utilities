<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Illuminate\Console\Command;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

class MakePodcastImages extends Command
{
    protected $signature = 'make:podcast-images';

    protected $description = 'Makes podcast images into the same directory as the file is from';

    protected Filesystem $podcastDisk;

    protected ImageManager $imageManager;

    protected ImageInterface $podcastWaveform;

    public function handle(ImageManager $imageManager): int
    {
        $this->imageManager = $imageManager; // done like this so that it's not auto init from the constructor

        $podcastLocation = $this->ask('Where is the file located?');

        $this->podcastDisk = Storage::build(['driver' => 'local', 'root' => dirname($podcastLocation)]);
        $podcastFileName = basename($podcastLocation);
        if (!$this->podcastDisk->exists($podcastFileName)) {
            throw new RuntimeException("The podcast image '$podcastFileName' can not be found.");
        }

        $this->createPodcastWaveform($podcastFileName);

        $this->youtube();

        $this->info('Success!');

        return static::SUCCESS;
    }

    protected function createPodcastWaveform(string $podcastFileName): void
    {
        $fullPodcastPath = $this->podcastDisk->path($podcastFileName);

        Process::path(Storage::disk('temp')->path(''))
            ->run("ffmpeg -i {$fullPodcastPath} -filter_complex showwavespic=split_channels=0:scale=lin:filter=peak:colors=white -frames:v 1 waveform.png")
            ->throw();

        $this->podcastWaveform = $this->imageManager->read(Storage::disk('temp')->path('waveform.png'));
    }

    protected function youtube(): void
    {
        $youtubeThumbnailLocation = $this->podcastDisk->path('youtube-thumbnail.jpg');

        $image = $this->imageManager->read(resource_path('templates/youtube-thumbnail.jpg'));
        $image->place($this->podcastWaveform, 'bottom-left', 100, 65, 75);
        $image->save($youtubeThumbnailLocation);

        $this->line("Wrote {$youtubeThumbnailLocation}");
    }
}
