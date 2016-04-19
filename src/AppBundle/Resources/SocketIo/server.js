var server = require('http').createServer(),
    io = require('socket.io')(server),
    logger = require('winston'),
    port = 1337;

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, {colorize: true, timestamp: true});
logger.info('SocketIO > listening on port ' + port);

var channels = {};

io.on('connection', function (socket) {
    var nb = 0;

    //logger.info('SocketIO > Connected socket ' + socket.id);

    // Server events

    socket.on('create_channel', function (message) {
        ++nb;
        logger.info('Init Channel "' + message.endpoint + '" (' + message.privateKey + ')');
        channels[message.privateKey] = message.endpoint;
    });

    socket.on('forward_notification', function (message) {
        ++nb;
        logger.info('Notification received: ' + JSON.stringify(message));
        // TODO forward to consumers
    });

    // Client Events

    socket.on('retrieve_configuration_from_private_key', function (message) {
        ++nb;
        logger.info('configuration retrieved: ' + JSON.stringify(message));
        // TODO forward to consumer
    });

    socket.on('subscribe_channel', function (message) {
        ++nb;
        logger.info('Channel subscription: ' + JSON.stringify(message));
        // TODO forward to consumer
    });

    socket.on('unsubscribe_channel', function (message) {
        ++nb;
        logger.info('Channel unsubscription: ' + JSON.stringify(message));
        // TODO forward to consumer
    });

    // socket.on('disconnect', function () {
    //     logger.info('SocketIO : Received ' + nb + ' messages');
    //     logger.info('SocketIO > Disconnected socket ' + socket.id);
    // });
});

server.listen(port);