Transcode video with FFmpeg in Laravel using queues. 

### Setup Instructions
```
$ composer install
$ npm install && npm run prod
$ php artisan key:generate

# update database credentials, queue connection driver and FFmpeg binaries
# if you're running app on Windows.
$ nano .env

QUEUE_CONNECTION=database

FFMPEG_BINARIES=''
FFPROBE_BINARIES=''

# connection settings for sqlite db
DB_CONNECTION=sqlite
DB_FILENAME=database/database.sqlite

$ touch database/database.sqlite
$ php artisan migrate
$ php artisan db:seed
```


#### Running queue worker
```
$ php artisan queue:work --tries=3 --queue=download,video --timeout=84600 --memory=1024
```
