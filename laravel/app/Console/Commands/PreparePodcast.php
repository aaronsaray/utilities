<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Illuminate\Console\Command;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class PreparePodcast extends Command
{
    protected $signature = 'podcast:prepare';

    protected $description = 'Reads a podcast mp3, creates an OG Image';

    protected ImageManager $imageManager;

    protected string $selectedMp3FullPath;

    protected string $podcastTitle;

    protected ImageInterface $podcastSummaryWaveformImage;

    public function handle(ImageManager $imageManager): int
    {
        $this->setDependency($imageManager);

        $this->configureFromUserInput();

        $this->createPodcastSummaryWaveformImage();

        $this->youtubePreviewImage();

        info('Success!');

        return static::SUCCESS;
    }

    // this is done like this so we don't have issues with injecting these in the constructor and it spinning up all the time
    protected function setDependency(ImageManager $imageManager): void
    {
        $this->imageManager = $imageManager;
    }

    protected function configureFromUserInput(): void
    {
        $podcastStorageBasePath = Storage::disk('podcast')->path('');

        $finder = new Finder();
        $finder->files()->name('*.mp3')->in($podcastStorageBasePath);

        if (!$finder->hasResults()) {
            throw new RuntimeException('No mp3 found in ' . $podcastStorageBasePath);
        }

        $this->selectedMp3FullPath = select(
            label: 'Choose your podcast mp3',
            options: iterator_to_array($finder),
        );

        $this->podcastTitle = text(
            label: 'What is the title of the podcast?',
            required: true,
            validate: ['title' => [
                'required',
                'string',
                'max:100',
            ]],
            hint: 'Title is limited to 100 characters.'
        );
    }

    protected function createPodcastSummaryWaveformImage(): void
    {
        $escapedPath = escapeshellarg($this->selectedMp3FullPath);

        Storage::disk('temp')->delete('waveform.png');
        Process::path(Storage::disk('temp')->path(''))
            ->run("ffmpeg -i {$escapedPath} -filter_complex showwavespic=split_channels=0:scale=lin:filter=peak:colors=white:s=620x160 -frames:v 1 waveform.png")
            ->throw();

        $this->podcastSummaryWaveformImage = $this->imageManager->read(Storage::disk('temp')->path('waveform.png'));
    }

    protected function youtubePreviewImage(): void
    {
        $youtubeThumbnailLocation = Storage::disk('podcast')->path('youtube-thumbnail.jpg');

        $image = $this->imageManager->read(resource_path('templates/youtube-thumbnail.jpg'));
        $image->place($this->podcastSummaryWaveformImage, 'bottom-left', 100, 110, 60);

        $image->text($this->podcastTitle, 98, 410, function (FontFactory $font) {
            $font->filename(resource_path('templates/SpaceGrotesk-Light.ttf'));
            $font->size(32);
            $font->color('#ffffff');
        });

        $image->save($youtubeThumbnailLocation);
    }
}
