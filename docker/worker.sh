#!/bin/bash

echo "Starting queue worker"
cd /opt/transcoding-webservice || exit
# Wait for database
mysql="mysql
            --host=db
            --user=root
            --password=root
            transcoding_webservice"
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

if [ ! -d vendor ]; then
  echo "vendor folder does not exist, please wait for setup finish"
  exit 1
fi

if [ ! -f .env ]; then
  echo ".env file does not exist, please wait for setup finish"
  exit 1
fi

/opt/transcoding-webservice/artisan queue:work --tries=3 --queue=download,video --timeout=84600 --memory=1024
