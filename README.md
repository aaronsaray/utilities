# Utilities
Mixed utilities I use. This is likely boring to you. :)

## MySQL

The MySQL is 8.3. Its data is stored in `docker/mysql/8.3/data` - you can start this with `cd docker && docker compose up -d mysql83`.

If using orbstack, you can connect with `root/password` to `mysql83.utilities.orb.local:3306`.

## Podcast Image Tools

This reads in a podcast of No Compromises, develops a visualization, and creates social media images in the same location as the file.

Run `laravel/artisan make:podcast-prepare` after you've placed your MP3 file in `laravel/storage/podcast`. It will prompt you to confirm which file and what the title is. All generated files will be placed in the same directory.

The names of the types of images will indicate their usages.

This requires ffmpeg to be installed. `brew install ffmpeg`
