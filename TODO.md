[WIP]

[Install process](https://github.com/lucascherifi/localhook-server/blob/master/README.md)

TODO LIST
=========

- [x] Symfony Server (2.8 LTS)

### Vendors

- [x] https://github.com/Wisembly/elephant.io

### Security
- [ ] ADMIN_ROLE
- [ ] USER_ROLE

### Controllers

#### AdminController

##### ListAction
- [x] List all webhooks
    - [ ] ADMIN_ROLE
- [ ] NewAction
    - [ ] USER_ROLE
- [ ] AddAction
    - [ ] USER_ROLE
    - [ ] Emit "CreateChannel" message
- [ ] EditAction
    - [ ] USER_ROLE
- [ ] DeleteAction
    - [ ] USER_ROLE

##### NotificationController
- [x] NotificationsAction
    - [x] ANONYMOUS_ROLE

### Entities

- [x] Webhook
    - [x] id
    - [x] private_key
    - [x] endpoint
    - [ ] userRole

### Commands

**app:start**

- [ ] start the socket.io server
- [x] Emit "CreateChannel" message for every Webhook entities

**app:stop**

- [ ] stop the socket.io server

**app:create-channel**

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

**php app/console configure-channel**

  - [ ] Ask hash
  - [ ] Emit "GetConfigurationFromHash" message
  - [ ] Handle error (not found)
  - [ ] Store configuration in a local yaml file

**php app/console run*

  - [ ] emit "SubscribeChannel" message (hash, id)
  - [ ] handle "Notification" message (then execute Guzzle requests)

**php app/console stop*

  - [ ] emit "UnsubscribeChannel" message

#### Install via composer (global)

- [ ] composer global require lucascherifi/localhook-client