#!/bin/bash

echo "Install Composer dependencies"

composer install --no-dev

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
    echo "SET time_zone='+00:00';" > ${SQL_FILE}

    i=0
    for num in $(shuf -i 1-$USER_RANDOM_MAX); do
      ((i++))
      RAND_STR=$(echo $num | md5sum | head -c 10)
      RAND_USER="${RAND_STR}-${num}"
      RAND_FLAG=$(shuf -i 0-1 -n 1)
      CURRENT_TS=$(date +%s)
      MONTH_TS=$((CURRENT_TS + 60 * 60 * 24 * 30))
      RAND_TS=$((CURRENT_TS + $RANDOM % MONTH_TS))

      echo >&2 "-- username: ${RAND_USER} (${i})"

      echo "INSERT INTO \`mailing\`.\`users\` (\`username\`, \`email\`, \`validts\`, \`confirmed\`) VALUES ('${RAND_USER}', '${RAND_USER}@example.com', ${RAND_TS}, ${RAND_FLAG}) ON DUPLICATE KEY UPDATE username='${RAND_USER}';"
    done >> ${SQL_FILE}

    gzip -c ${SQL_FILE} > "${SQL_FILE}.gz"
    rm ${SQL_FILE}
  fi

  echo "- Importing dump file into DB"
  gunzip < "${SQL_FILE}.gz" | mysql -h${MYSQL_HOST} -u${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE}

  echo "- Complete"
fi

exec "$@"
