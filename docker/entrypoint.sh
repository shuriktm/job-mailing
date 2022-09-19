#!/bin/bash

env >> /etc/environment
echo "MYSQL_PASSWORD='${MYSQL_PASSWORD}'" >> /etc/environment

SQL_EXISTS=$(printf 'SHOW TABLES LIKE "%s"' "emails")
if [[ $(mysql -h "${MYSQL_HOST}" -u "${MYSQL_USER}" -p "${MYSQL_PASSWORD}" -e "$SQL_EXISTS" "${MYSQL_DATABASE}") ]]; then
  echo "Database already created"
else
  echo "Create database"
#  SQL_FILE="./data/db-init.sql"
#  if [ $? -eq 0 ]; then
#    echo "Dump file found";
#  else
#    echo "Create dump file with random data";
#    for i in {1..1000000} ; do
#        RAND_STR=$(echo $RANDOM | md5sum | head -c 20)
#    done
#    # TODO: Create dump file
#  fi;
#  mysql -f -h${MYSQL_HOST} -u${MYSQL_USERNAME} -p${MYSQL_PASSWORD} ${MYSQL_DBNAME} < ${SQL_FILE}
#  rm ${SQL_FILE}
  echo "Done";
fi;

#tail -f /dev/null
