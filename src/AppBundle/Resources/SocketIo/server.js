var server = require('http').createServer(),
    io = require('socket.io')(server),
    logger = require('winston'),
    port = process.env.SOCKET_IO_PORT;

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, {colorize: true, timestamp: true});
logger.info('SocketIO listening on port ' + port);

var channels = {};
var clients = [];

io.on('connection', function (socket) {
    var nb = 0;

    logger.info(socket.id + ' > Connected socket');
    //socket.emit('answer_create_channel', {'status': 'ok'});
    clients.push(socket.id);

    // Server events

    socket.on('create_channel', function (message) {
        ++nb;
        logger.info(socket.id + ' > Init channel "' + message.endpoint + '" (' + message.privateKey + ')');
        channels[message.endpoint] = {"privateKey": message.privateKey};
        socket.emit('answer_create_channel', {'status': 'ok'});
    });

    socket.on('delete_channel', function (message) {
        ++nb;
        logger.info(socket.id + ' > Delete channel "' + message.endpoint + '" (' + message.privateKey + ')');
        delete channels[message.endpoint];
        socket.broadcast.to(message.endpoint).emit('deleted_channel', {"endpoint": message.endpoint});

        socket.emit('answer_delete_channel', {'status': 'ok'});
    });

    socket.on('forward_notification', function (message) {
        ++nb;
        logger.info(
            socket.id + ' > Notification received: ' + JSON.stringify(message) +
            ' forwarded to clients: ' + JSON.stringify(clients)
        );
        socket.broadcast.to(message.webHookEndpoint).emit('forwarded_notification', {
            "method": message.method,
            "headers": message.headers,
            "query": message.query,
            "request": message.request
        });
    });

    // Client Events

    socket.on('retrieve_configuration_from_private_key', function (message) {
        ++nb;
        var foundWebHook = null;
        for (var webHook in channels) {
            // skip loop if the property is from prototype
            if (!channels.hasOwnProperty(webHook)) continue;
            if (message.privateKey == channels[webHook].privateKey) {
                foundWebHook = webHook;
            }
        }
        var response = {'endpoint': foundWebHook};
        socket.emit('answer_retrieve_configuration_from_private_key', response);
        logger.info(
            socket.id + ' > configuration sent for private key ' + message.privateKey + ': ' + JSON.stringify(response)
        );
    });

    socket.on('subscribe_channel', function (message) {
        ++nb;
        var channel = message.channel;
        var privateKey = message.privateKey;
        if (channels[channel] && channels[channel]['privateKey'] == privateKey) {
            socket.join(channel);
            logger.info(socket.id + ' > Channel subscription: ' + channel + ' for client ' + socket.id);
            socket.emit('answer_subscribe_channel', {'status': 'ok'});
        } else {
            socket.emit('answer_subscribe_channel', {'status': 'error', 'message': 'The private key does not match'});
        }
    });

    socket.on('unsubscribe_channel', function (message) {
        ++nb;
        socket.leave(message.channel);
        socket.emit('answer_unsubscribe_channel', {'status': 'ok'});
        logger.info(socket.id + ' > Channel unsubscription: ' + JSON.stringify(message));

    });

    socket.on('disconnect', function () {
        //logger.info('SocketIO : Received ' + nb + ' messages');
        var index = clients.indexOf(socket.id);
        clients.splice(index, 1);
        logger.info(socket.id + ' > Disconnected socket');
    });
});

server.listen(port);