# Setup
start db (docker compose up -d)

initial setup stuff
php artisan migrate



create user: php artisan user:create <username> <password>





open php builtin webserver inside WSL to lan: netsh interface portproxy add v4tov4 listenport=8000 listenaddress=0.0.0.0 connectport=8000 connectaddress=172.26.202.84 

