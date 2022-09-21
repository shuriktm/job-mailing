# Test Subscription service

Installation
---------------

- Install [Docker](https://www.docker.com/)
- Clone the repo to the project directory
- Run `docker-compose up` in the project directory

Usage
-----

- Open dashboard on [127.0.0.1](http://127.0.0.1/)
- Cron job is planned automatically
- On dashboard you can see the mailing progress

Database
----------

- MySQL server is available on IP 127.0.0.1 and port 3306

Developers
----------

- You can adjust servers IPs and ports in `docker-compose.yml` if it conflicts with your local environment
- Random data is automatically generated (1 million users), set `USER_RANDOM_MAX=1000000` environment variable for `php` docker container to change number of users
- To speed up testing set `USE_SAMPLE_DATA=true` environment variable for `php` docker container (set by default)
