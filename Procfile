release: mkdir db && chmod 0777 db
release: [ -f "env.php" ] || cp env.php.original env.php

web: heroku-php-apache2
