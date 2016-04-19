[WIP]

[TODO list](https://github.com/lucascherifi/localhook-server/blob/master/TODO.md)

Installation guide
------------------

- clone this project
```bash
cd src/AppBundle/Resources/SocketIo && npm install
```

```bash
php app/console d:d:c
php app/console d:s:u --force
php app/console h:d:f:l -n
```

Start the app!
--------------

### Socket IO server:

```bash
cd src/AppBundle/Resources/SocketIo && node server.js
```

### Run tests:

```bash
phpunit -c app
```

### Admin interface:

```bash
php app/console server:run
open http://localhost:8000/app_dev.php/
```