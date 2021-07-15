#!/bin/bash

function setup_database()
{
  echo "Setup database"

  mysql="mysql
            --host=$DB_HOST
            --user=$DB_USERNAME
            --password=$DB_PASSWORD
            $DB_DATABASE"

        # Wait for database
        tries=0
        maxTries=100
        until ${mysql} -e "SELECT VERSION();" &> /dev/null; do
            tries=$((tries + 1))
            if [ $tries -gt $maxTries ]; then
                # give up
                echo "Could not connect to database, aborting"
                exit 1
            fi
            echo "Cannot connect to database, waiting"
            sleep 3
        done
        echo "Database connection established"

        if ! $(${mysql} -e "SELECT COUNT(*) FROM users" &> /dev/null); then
            # Install database
            ${mysql} -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
            echo "Installing database"
	          php artisan migrate
	          php artisan db:seed

            echo "======================================================="
            echo "transcoding webservice installed successfully!"
            echo "Log in using the following credentials:"
            echo
            echo "User:           admin@example.org"
            echo "Password:       admin"
            echo "======================================================="
        else
            echo "transcoding webservice already installed"
        fi
}

APP_URL="${APP_URL:=http://0.0.0.0:8000}"
echo "APP_URL: ${APP_URL}"

cd /opt/transcoding-webservice || exit

#if [ ! -d vendor ]; then
 	echo "Running composer and npm install"
  composer install
  npm install
  npm run prod
#fi

#if [ ! -f .env ]; then
	echo "Generating .env file"
	echo "APP_NAME=$APP_NAME" > .env
	echo "APP_KEY=$APP_KEY" >> .env
	echo "APP_ENV=$APP_ENV" >> .env
	echo "APP_DEBUG=$APP_DEBUG" >> .env
	echo "APP_URL=$APP_URL" >> .env
	echo "LOG_CHANNEL=$LOG_CHANNEL" >> .env
	echo "DB_CONNECTION=$DB_CONNECTION" >> .env
	echo "DB_DATABASE=$DB_DATABASE" >> .env
  echo "DB_HOST=$DB_HOST" >> .env
  echo "DB_PORT=$DB_DB_PORT" >> .env
  echo "DB_USERNAME=$DB_USERNAME" >> .env
  echo "DB_PASSWORD=$DB_PASSWORD" >> .env
	echo "BROADCAST_DRIVER=$BROADCAST_DRIVER" >> .env
	echo "CACHE_DRIVER=$CACHE_DRIVER" >> .env
	echo "QUEUE_CONNECTION=$QUEUE_CONNECTION" >> .env
	echo "SESSION_DRIVER=$SESSION_DRIVER" >> .env
	echo "SESSION_LIFETIME=$SESSION_LIFETIME" >> .env
	echo "FFMPEG_DEBUG=$FFMPEG_DEBUG" >> .env
	echo "FFPROBE_DEBUG=$FFPROBE_DEBUG" >> .env
  echo "MINIO_ENDPOINT=$MINIO_ENDPOINT" >> .env
  echo "AWS_KEY=$AWS_KEY" >> .env
  echo "AWS_SECRET=$AWS_SECRET" >> .env
  echo "AWS_REGION=$AWS_REGION" >> .env
  echo "AWS_BUCKET=$AWS_BUCKET" >> .env
  echo "FFMPEG_THREADS=$FFMPEG_THREADS" >> .env
  echo "FFMPEG_TIMEOUT=$FFMPEG_FFMPEG_TIMEOUT" >> .env
  echo "ZIPSTREAM_AWS_PATH_STYLE_ENDPOINT=$ZIPSTREAM_AWS_PATH_STYLE_ENDPOINT" >> .env

  setup_database
#fi

php /opt/transcoding-webservice/artisan serve --host=0.0.0.0 --port=8000
