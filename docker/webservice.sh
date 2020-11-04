#!/bin/bash

function setup_database()
{
  echo "Setup database"

  mysql="mysql
            --host=db
            --user=root
            --password=root
            transcoding_webservice"

        # Wait for database
        tries=0
        maxTries=20
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
            ${mysql} -e "CREATE DATABASE IF NOT EXISTS transcoding_webservice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
            echo "Installing database"
	          php artisan migrate
	          php artisan db:seed

	          echo "APP_KEY=$(php artisan key:generate --show)" >> .env

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


if [ ! -f .env ]; then
	echo "Generating .env file"
	echo "APP_NAME=Laravel" >> .env
	echo "APP_ENV=local" >> .env
	echo "APP_DEBUG=true" >> .env
	echo "APP_URL=${APP_URL}" >> .env
	echo "LOG_CHANNEL=stack" >> .env
	echo "DB_CONNECTION=mysql" >> .env
	echo "DB_DATABASE=transcoding_webservice" >> .env
  echo "DB_HOST=db" >> .env
  echo "DB_PORT=3306" >> .env
  echo "DB_USERNAME=root" >> .env
  echo "DB_PASSWORD=root" >> .env
	echo "BROADCAST_DRIVER=log" >> .env
	echo "CACHE_DRIVER=file" >> .env
	echo "QUEUE_CONNECTION=database" >> .env
	echo "SESSION_DRIVER=file" >> .env
	echo "SESSION_LIFETIME=120" >> .env
	echo "FFMPEG_DEBUG=true" >> .env
	echo "FFPROBE_DEBUG=true" >> .env

  setup_database

	if [ ! -d vendor ]; then
        	echo "Running composer and npm install"
	        composer install
	        npm install
	        npm run prod
	fi
fi



/opt/transcoding-webservice/artisan serve --host=0.0.0.0 --port=8000
