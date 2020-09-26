release: mkdir db && chmod 0777 db
release: [ -f "xenv.php" ] || cp env.php.original env.php

web: heroku-php-apache2
