[WIP]

[TODO list](https://github.com/lucascherifi/localhook-server/blob/master/TODO.md)

Installation
------------

- clone this project
- cd src/AppBundle/Resources/SocketIo && npm install
- php app/console d:d:c && php app/console d:s:u --force && php app/console h:d:f:l -n

Start the app
-------------

### Socket IO server:

- cd src/AppBundle/Resources/SocketIo && node server.js

### Run tests:

- phpunit -c app

### Admin interface:

- php app/console server:run
- open http://localhost:8000/app_dev.php/