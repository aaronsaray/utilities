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
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class PreparePodcast extends Command
{
    protected $signature = 'podcast:prepare';

    protected $description = 'Reads a podcast mp3, creates an OG Image';

    protected ImageManager $imageManager;

    protected string $selectedMp3FullPath;

    protected string $mp3BaseName;

    protected string $podcastTitle;

    protected ImageInterface $podcastSummaryWaveformImage;

    public function handle(ImageManager $imageManager): int
    {
        $this->setDependency($imageManager);

        $this->configureFromUserInput();

        note('Generating waveform...');
        $this->createPodcastSummaryWaveformImage();
        info('Done.');

        note('Generating Youtube thumbnail...');
        $this->generateYoutubeThumbnail();
        info('Done.');

        note('Generating Youtube waveform video...');
        $this->generateYoutubeWaveformVideo();
        info('Done.');

        if (confirm(label: 'Do you want to open the output folder?', default: true)) {
            Process::path(Storage::disk('podcast')->path(''))->run('open .');
        }

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
            pause("No mp3 found in [{$podcastStorageBasePath}]. We'll open that folder now.");
            Process::path($podcastStorageBasePath)->run(sprintf('open %s', escapeshellarg($podcastStorageBasePath)));
            throw new RuntimeException('No mp3 found in ' . $podcastStorageBasePath);
        }

        $this->selectedMp3FullPath = select(
            label: 'Choose your podcast mp3',
            options: iterator_to_array($finder),
        );

        $this->mp3BaseName = basename($this->selectedMp3FullPath);

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

    protected function generateYoutubeThumbnail(): void
    {
        $youtubeThumbnailLocation = Storage::disk('podcast')->path(sprintf('%s.youtube-thumbnail.jpg', $this->mp3BaseName));

        $image = $this->imageManager->read(resource_path('templates/youtube-thumbnail.jpg'));
        $image->place($this->podcastSummaryWaveformImage, 'bottom-left', 100, 110, 60);

        $image->text($this->podcastTitle, 98, 410, function (FontFactory $font) {
            $font->filename(resource_path('templates/SpaceGrotesk-Light.ttf'));
            $font->size(32);
            $font->color('#ffffff');
        });

        $image->save($youtubeThumbnailLocation);
    }

    protected function generateYoutubeWaveformVideo(): void
    {
        $youtubeVideoFileName = sprintf('%s.youtube-video.mp4', $this->mp3BaseName);
        $imagePath = resource_path('templates/youtube-thumbnail.jpg');
        $fontPath = resource_path('templates/SpaceGrotesk-Light.ttf');

        spin(fn() => Process::path(Storage::disk('temp')->path(''))
            ->run(sprintf(
                'ffmpeg -i %s -i %s -filter_complex "[0:a]showwaves=mode=p2p:s=620x160:scale=sqrt:n=2:colors=0xeeeeff[fg];[1:v]scale=1280:720[bg];[bg][fg]overlay=x=100:y=450,drawtext=text=\'%s\':fontsize=32:fontcolor=white:fontfile=%s:x=100:y=400[outv]" -map "[outv]" -map 0:a -pix_fmt yuv420p %s',
                escapeshellarg($this->selectedMp3FullPath),
                $imagePath,
                $this->podcastTitle,
                $fontPath,
                escapeshellarg($youtubeVideoFileName),
                ))
            ->throw()
        );

        Storage::disk('podcast')->writeStream($youtubeVideoFileName, Storage::disk('temp')->readStream($youtubeVideoFileName));
        Storage::disk('temp')->delete($youtubeVideoFileName);
    }
}
