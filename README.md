[WIP]

[TODO list](https://github.com/lucascherifi/localhook-server/blob/master/TODO.md)

Installation guide
------------------

- clone this project

```bash
composer install
php app/console d:d:c
php app/console d:s:u --force
php app/console h:d:f:l -n
cd src/AppBundle/Resources/SocketIo && npm install
```

Start the app!
--------------

### Admin interface:

```bash
php app/console server:run -v
open http://localhost:8000/app_dev.php/
```

### Socket IO server:

```bash
php app/console app:server:run-socket-io
```

### Watch a notification:

```bash
php app/console app:client:watch-notification webhook_1
```

### Simulate a notification:

```bash
php app/console app:server:simulate-notification
```

### Run workflow tests:

```bash
phpunit -c app src/AppBundle/Tests/Functional/WorkflowTest.php
```
