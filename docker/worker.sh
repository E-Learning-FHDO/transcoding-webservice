#!/bin/bash

echo "Starting queue worker"

# Wait for database
mysql="mysql
            --host=db
            --user=root
            --password=root
            transcoding_webservice"
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

/opt/transcoding-webservice/artisan queue:work --tries=3 --queue=download,video --timeout=84600 --memory=1024
