# Local Music Upload Limits

Custom and catalog music uploads have a business limit of 10 MB.

Laravel validation uses:

- `MAX_MUSIC_SIZE=10240`
- `MAX_MUSIC_SIZE_MB=10`
- Error message: `Ukuran file musik melebihi batas maksimum 10 MB.`

PHP must allow a slightly larger request body before Laravel can validate the file:

- `upload_max_filesize >= 12M`
- `post_max_size >= 16M`

For PHP-FPM/CGI local setups, `public/.user.ini` sets these values for this app. If using `php artisan serve` or another runtime that ignores `.user.ini`, start PHP with equivalent ini values or update the loaded `php.ini`.
