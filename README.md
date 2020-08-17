Transcode video with FFmpeg in Laravel using queues. 

The webservice is based on the PHP framework Laravel and currently uses the database SQLite, but can also be used with other databases. 
At least PHP version 7.3 is required. Furthermore the following packages and PHP extensions are required: php-fpm, php-sqlite3 php-xml php-zip php-curl as well as nginx, nodejs, composer and ffmpeg.


### Setup Instructions

Copy the sample environment configuration file:
```
$ cp env.sample .env
```
set APP_URL to your webserver URL.
Update database credentials, queue connection driver and FFmpeg binaries in .env
Set queue connection to database or beanstalkd:
```
QUEUE_CONNECTION=database
```
Set paths to ffmpeg and ffprobe binaries, if they are not located in standard path:
```
FFMPEG_BINARIES=''
FFPROBE_BINARIES=''
```
Set database connection settings, here for sqlite. Usage of other database backends such as mysql is also possible, 
please refer to laravel documentation for setup instructions.
```
DB_CONNECTION=sqlite
DB_FILENAME=database/database.sqlite
```
Save and close the .env file

Run composer to install php dependencies 
```
$ composer install
```
Run npm to install nodejs dependencies 
```
$ npm install && npm run prod
$ php artisan key:generate
```

Run database migrations
```
$ touch database/database.sqlite
$ php artisan migrate
$ php artisan db:seed
```
#### Configure webservice as NGINX site
Use following site configuration for the webservice, 
adjust server name and root path.
NGINX should be started with www-data user
```
server {
    listen 80;
    server_name server.name;
    root /opt/transcoding-webservice/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

```
#### Running queue worker
```
$ php artisan queue:work --tries=3 --queue=download,video --timeout=84600 --memory=1024
```

#### Install queue worker as systemd service
Use the following systemd unit file to run the queue worker as systemd service,
adjust user, group and paths to your specific needs
```
[Unit]
Description=Transcoding Webservice queue worker
 
[Service]
User=www-data
Group=www-data
Restart=on-failure
ExecStart=/usr/bin/php /opt/transcoding-webservice/artisan queue:work --daemon --tries=3 --queue=download,video --timeout=84600 --memory=1024
 
[Install]
WantedBy=multi-user.target
```
