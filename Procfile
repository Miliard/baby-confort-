web: php artisan storage:link --force; php artisan migrate --force; php artisan schedule:work > /dev/null 2>&1 & PHP_CLI_SERVER_WORKERS=8 php artisan serve --host 0.0.0.0 --port $PORT
