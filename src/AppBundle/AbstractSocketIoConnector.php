<?php

namespace AppBundle;

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version1X;
use Exception;

abstract class AbstractSocketIoConnector
{
    /** @var Client */
    protected $client;

    /** @var string */
    protected $socketIoPort;

    public function __construct($socketIoPort)
    {
        $this->socketIoPort = $socketIoPort;
    }

    /**
     * @return $this
     */
    public function ensureConnection()
    {
        if (!$this->client) {
            $this->client = new Client(new Version1X('http://localhost:' . $this->socketIoPort));
            $this->client->initialize();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function closeConnection()
    {
        $this->client->close();

        return $this;
    }

    /**
     * @param array $messageKeys
     *
     * @return bool
     */
    protected function waitForMessage($messageKeys = [])
    {
        while (true) {
            $r = $this->client->read();
            if (!empty($r)) {
                $input = json_decode(substr($r, 2), true);
                $messageKey = $input[0];
                if (!count($messageKeys) || in_array($messageKey, array_keys($messageKeys))) {
                    return $input[1];
                }
            }
        }
        return false;
    }

    protected function ask($messageKey, $messageContent)
    {
        $this->client->emit($messageKey, $messageContent);

        return $this->waitForMessage(['answer_' . $messageKey]);
    }

    protected function emitAndCheck($messageKey, $messageContent)
    {
        $result = $this->ask($messageKey, $messageContent);

        if ($result['status'] == 'error') {
            throw new Exception('Socket IO server failed action with error: ' . $result['message']);
        }
    }
}
