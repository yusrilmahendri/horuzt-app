# Local Music Upload Limits

Personal music uploads have a business limit of 20 MB.

Laravel validation uses:

- `MAX_MUSIC_SIZE=20480`
- `MAX_MUSIC_SIZE_MB=20`
- Error message: `Ukuran file musik maksimal 20 MB.`

PHP must allow a slightly larger request body before Laravel can validate the file:

- `upload_max_filesize >= 25M`
- `post_max_size >= 30M`

For PHP-FPM/CGI local setups, `public/.user.ini` sets these values for this app. If using `php artisan serve` or another runtime that ignores `.user.ini`, start PHP with equivalent ini values or update the loaded `php.ini`.
