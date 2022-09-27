# Test Mailing service

In current configuration the service can:

- check 6000 emails per hour (that is about 140000 per day or 4 millions per month)
- send the same number of emails per hour
- the load is evenly distributed over time

Set `CHECK_THREAD_MAX=100` and `SEND_THREAD_MAX=20` environment variable for `php` docker container to manage service capabilities (maximum sum of threads is 300).

Installation
------------

- Install [Docker](https://www.docker.com/)
- Clone the repo to the project directory
- Run `docker-compose up` in the project directory

Usage
-----

- Open dashboard on [localhost](http://localhost/)
- Cron job is planned automatically
- Dashboard displays general statistics, check and mailing progress

Database
--------

- MySQL server is available on IP 127.0.0.1 and port 3306

Developers
----------

- You can adjust servers IPs and ports in `docker-compose.yml` if it conflicts with your local environment
- Random data is automatically generated (1 million users), set `USER_RANDOM_MAX=1000000` environment variable for `php` docker container to change number of users
- To speed up testing, set `USER_SAMPLE_DATA=true` environment variable for `php` docker container (set by default)
