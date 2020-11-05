Docker-based environment of the transcoding webservice for testing purposes containing following services:

- webservice: PHP-based web server for the API and admin panel
- db: MariaDB for persistence
- worker-1, worker-2: queue worker

### Setup Instructions

- Copy .env.example to .env and modify ```APP_URL``` to your docker host IP address/FQDN.
- Run ```docker-compose up``` inside this directory.