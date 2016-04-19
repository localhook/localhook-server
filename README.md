[WIP]

TODO LIST
=========

- [x] Symfony Server (2.8 LTS)

### Vendors

- [x] https://github.com/Wisembly/elephant.io

### Controller

#### AdminController

##### ListAction
- [x] List all webhooks
- [ ] ADMIN_ROLE
- [ ] NewAction # USER_ROLE
- [ ] AddAction # USER_ROLE / Emit "CreateChannel" message
- [ ] EditAction # USER_ROLE
- [ ] DeleteAction # USER_ROLE

##### NotificationController
- [x] NotificationsAction # ANONYMOUS_ROLE

### Entity

[x] Webhook
- [x] id
- [x] private_key
- [x] endpoint
- [ ] userRole

### Command

app:start
- [ ] start the socket.io server
- [x] Emit "CreateChannel" message for every Webhook entities
app:stop
- [ ] stop the socket.io server
app:create-channel
- [x] create a channel with command line
- [ ] autogenerate private key


Socket.io Server
----------------

### Events

- [x] create_channel # Add a channel
- [x] transfer_notification # Emit a notification to every subscribed client of a specific channel

- [ ] retrieve_configuration_from_private_key(private_key) # Return configuration (private_key, endpoint)
- [ ] subscribe_channel # Link a client to a channel
- [ ] unsubscribe_channel # Unlink a client to a channel

### Client

#### Vendors
- [ ] https://github.com/Wisembly/elephant.io
- [ ] guzzle
#### Commands
configure-channel
  - [ ] Ask hash
  - [ ] Emit "GetConfigurationFromHash" message
  - [ ] Handle error (not found)
  - [ ] Store configuration in a local yaml file
run
  - [ ] emit "SubscribeChannel" message (hash, id)
  - [ ] handle "Notification" message (then execute Guzzle requests)
stop
  - [ ] emit "UnsubscribeChannel" message

#### Install via composer
- [ ] composer global require

Server install process
----------------------

- clone this project
- cd src/AppBundle/Resources/SocketIo && npm install
- php app/console d:d:c && php app/console d:s:u --force && php app/console h:d:f:l -n

Start
-----

### Socket IO server:

- cd src/AppBundle/Resources/SocketIo && node server.js

### Run tests:

- phpunit -c app

### Admin interface:

- php app/console server:run
- open http://localhost:8000/app_dev.php/