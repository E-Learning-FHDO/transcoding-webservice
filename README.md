### Purpose
Transcode video with FFmpeg in Laravel using queues. 

The main goal of this webservice is to reach faster conversion of video files in several browser-compatible formats and 
qualities through the ability of horizontal scaling of CPU-based encoding (FFmpeg with libx264). 
The webservice also provides the possibility to use GPU-based encoding (VAAPI or NVENC support needs to be enabled in FFmpeg at compile time).


### How it works
This webservice offers a RESTful API for submission of video URLs and conversion parameters (described in API endpoints).
The video will be downloaded and queued for conversion using FFmpeg with submitted parameters (e.g. resolution, bitrate) 
by the webservice after submission.

The webservice consists of two parts: 
- webservice itself for offering the API endpoints and a user interface for its administration
- one or many queue workers for processing of download and conversion jobs

Webservice and workers have to use shared storage (such as NFS) for the project root to access uploaded and converted videos.

### Prerequisites
The webservice is based on the PHP framework Laravel. 
At least PHP version 7.3 is required. Furthermore, the following packages and PHP extensions are required: 
php-fpm, php-mysql, php-xml, php-zip, php-curl, php-mbstring, php-intl as well as nginx, nodejs, composer and ffmpeg.


### Setup Instructions
This instruction should fit for most Debian-based Linux distributions.

#### Webservice setup
- Install required packages on the webservice host:
```
$ apt-get update && apt-get install -y --no-install-recommends \
wget git unzip nginx composer php7.3-fpm php7.3-cli php7.3-mysql \
php7.3-xml php7.3-zip php7.3-curl php7.3-intl php7.3-mbstring \
php7.3-json php7.3-gd mariadb-server mariadb-client

$ wget --quiet -O - https://deb.nodesource.com/setup_12.x | bash - \
    && apt-get update && apt-get install -y nodejs
```

- Download the project files via git to preferred directory (e.g. /opt/transcoding-webservice) and get into it:
```
$ cd /opt
$ git clone https://github.com/E-Learning-FHDO/transcoding-webservice.git
$ cd transcoding-webservice
```

- Copy the sample environment configuration file:
```
$ cp env.sample .env
```
Edit .env with your favorite text editor:
- Set APP_URL to your webserver URL.
- Update database credentials, queue connection driver and FFmpeg binaries in .env
- Set queue connection to database or beanstalkd:
```
QUEUE_CONNECTION=database
```
- Set paths to ffmpeg and ffprobe binaries, if they are not located in a standard path:
```
FFMPEG_BINARIES=''
FFPROBE_BINARIES=''
```
- Set database connection settings. MySQL or MariaDB is necessary for concurrent worker connections.
Other database backends might also work, but haven't been tested extensively.
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=transcoding_webservice
DB_USERNAME=root
DB_PASSWORD=
```
- Save and close the .env file
- Run composer to install php dependencies 
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
$ php artisan migrate
$ php artisan db:seed
```
##### Configure webservice as NGINX site
Use following site configuration for the webservice, 
adjust server name and root path.
NGINX should be started with www-data user.
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
##### Configure cron job for task scheduling
Create file /etc/cron.d/transcoding-webservice with following content, adjust the path according your needs
```
* * * * * root cd /opt/transcoding-webservice && php artisan schedule:run >> /dev/null 2>&1
```
#### Worker setup
The worker should be able to access the project root using the same shared storage, which the webservice has access to. 
Therefore, it should be connected e.g. via NFS-mountpoint. 

- Install required packages on the worker host:
```
$ apt-get update && apt-get install -y --no-install-recommends \
ffmpeg php7.3-cli php7.3-mysql nfs-common \
php7.3-xml php7.3-zip php7.3-curl php7.3-intl php7.3-mbstring \
php7.3-json php7.3-gd mariadb-client
```

##### Running queue worker
```
$ php artisan queue:work --tries=3 --queue=download,video --timeout=84600 --memory=1024
```

##### Install queue worker as systemd service
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

#### Troubleshooting
Enable full FFmpeg and FFprobe output in storage/log/laravel.log for debugging purposes: 
```
FFMPEG_DEBUG=true
FFPROBE_DEBUG=true
```

### Workflow
### API Endpoints
Webservice


|  Endpoint | Description |
| :--- | :--- |
| <webservice-URL>/api/transcode | |   
| <webservice-URL>/api/download/<target-file>.mp4 | | 
| <webservice-URL>/api/download/<target-file>.mp4/finished | |
| <webservice-URL>/api/status/<mediakey> | | 
| <webservice-URL>/api/delete/<mediakey> | | 


Plugin

|  Endpoint | Description |
| :--- | :--- |
| <plugin-URL>/transcoderwebservice/source/<source-file>.mp4 | |
| <plugin-URL>/transcoderwebservice/callback | | 

transcoderwebservice/callback

```json
{
     "api_token": "cb209032d8cf33365b5bd63bc032737b",
     "mediakey": "305a9f185f5a9bb047786cdadee28d3a",
     "medium": {
       "label": "Default",
       "url": "http://172.17.0.1:8000/api/download/305a9f185f5a9bb047786cdadee28d3a_1568721590.mp4"   
     },
     "properties": {
       "width": 640,
       "height": 360,
       "filesize": 123,
       "duration": 5,
       "source-width": 1920,
       "source-height": 1080
     }
}
```