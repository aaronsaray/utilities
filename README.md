# Utilities
Mixed utilities I use. This is likely boring to you. :)

## MySQL

The MySQL is 8.3. Its data is stored in `mysql/8.3/data` - you can start this with `docker compose up -d mysql83`.

If using orbstack, you can connect with `root/password` to `mysql83.utilities.orb.local:3306`.

## Podcast Image Tools

This reads in a podcast of No Compromises, develops a visualization, and creates social media images in the same location as the file.

Run `laravel/artisan make:podcast-images` and be prepared to submit the location of the mp3 file for the podcast.

The names of the types of images will indicate their usages.

This requires ffmpeg to be installed. `brew install ffmpeg`
