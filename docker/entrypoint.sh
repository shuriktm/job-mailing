#!/bin/bash

echo "Install Composer dependencies"

composer install --no-dev
composer update --no-dev

env >> /etc/environment
echo "MYSQL_PASSWORD='${MYSQL_PASSWORD}'" >> /etc/environment

echo "Waiting MySQL"

while ! mysqladmin ping -h${MYSQL_HOST} --silent; do
  sleep 1
done

echo "MySQL available"

USER_SQL="SELECT COUNT(*) AS count FROM users LIMIT 1"
USER_RESULT=$(mysql -h${MYSQL_HOST} -u${MYSQL_USER} -p${MYSQL_PASSWORD} -e "${USER_SQL}" -D ${MYSQL_DATABASE})
USER_COUNT=$(echo $USER_RESULT | cut -d' ' -f 2)

if (($((USER_COUNT)) > 0)); then
  echo "Users already created"
else
  echo "Create random users:"
  SQL_FILE="/var/www/mailing/docker/data/mailing-random.sql"

  # Use sample data by default
  if [ -z ${USER_SAMPLE_DATA+x} ]; then USER_SAMPLE_DATA='true'; fi

  # Copy sample data to import into DB
  SAMPLE_FILE="/var/www/mailing/docker/mysql/mailing-sample.sql.gz"
  if [ "${USER_SAMPLE_DATA}" = 'true' ] && [ -f "$SAMPLE_FILE" ]; then
    echo "- Use sample data"
    cp $SAMPLE_FILE "$SQL_FILE.gz"
  fi

  if [ -f "${SQL_FILE}.gz" ]; then
    echo "- Dump file exist"
  else
    # 1 million users by default
    if [ -z ${USER_RANDOM_MAX+x} ]; then USER_RANDOM_MAX=1000000; fi

    echo "- Creating dump file with random users data"
    touch ${SQL_FILE}

    CURRENT_TS=$(date +%s)
    START_TS=$((CURRENT_TS + 60 * 60 * 24 * 3))
    END_TS=$((START_TS + 60 * 60 * 24 * 30))

    i=0
    for num in $(shuf -i 1-$USER_RANDOM_MAX); do
      ((i++))
      RAND_STR=$(echo $num | md5sum | head -c 10)
      RAND_USER="${RAND_STR}-${num}"
      RAND_FLAG=$(shuf -i 0-1 -n 1)
      RAND_TS=$(shuf -i $START_TS-$END_TS -n 1)

      echo >&2 " - username: ${RAND_USER} (${i})"

      echo "INSERT INTO \`mailing\`.\`users\` (\`username\`, \`email\`, \`validts\`, \`confirmed\`) VALUES ('${RAND_USER}', '${RAND_USER}@example.com', ${RAND_TS}, ${RAND_FLAG});"
    done >> ${SQL_FILE}

    DUMP_TS=$(date +%s)
    DUMP_TIME=$(date -d @$((DUMP_TS - CURRENT_TS)) +%H:%M:%S)

    echo "- Creation time: ${DUMP_TIME}"

    gzip -c ${SQL_FILE} > "${SQL_FILE}.gz"
    rm ${SQL_FILE}
  fi

  echo "- Importing dump file into DB"
  gunzip < "${SQL_FILE}.gz" | mysql -h${MYSQL_HOST} -u${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE}

  IMPORT_TS=$(date +%s)
  IMPORT_TIME=$(date -d @$((IMPORT_TS - DUMP_TS)) +%H:%M:%S)

  echo "- Import time: ${IMPORT_TIME}"

  echo "- Complete"
fi

echo "Initialize crontab"

crontab /var/www/mailing/docker/cron/crontab.txt

exec "$@"
