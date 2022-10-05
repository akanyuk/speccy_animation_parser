#!/bin/bash

if [ "$#" -eq  "0" ]; then
  service php7.2-fpm start
  service nginx start
else
  php /www/cmd.php "$@"
fi
