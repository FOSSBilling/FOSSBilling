#!/bin/bash
# Generates dump of provided database structure and save it to src/install/structure.sql

if [ -z $1 ] || [ -z $2 ]; then
  echo "error: arguments not found" >&2
  echo "usage: $0 database username" >&2
  exit 1
fi

db_name=$1
db_user_name=$2

mysqldump --skip-add-drop-table -d -h localhost -u $db_user_name -p $db_name | sed 's/ AUTO_INCREMENT=[0-9]*\b//' > ../src/install/structure.sql