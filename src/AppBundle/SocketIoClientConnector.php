<?php

namespace AppBundle;

class SocketIoClientConnector extends AbstractSocketIoConnector
{
    /**
     * @param string $privateKey
     *
     * @return $this
     */
    public function retrieveConfigurationFromPrivateKey($privateKey)
    {
        return $this->ask('retrieve_configuration_from_private_key', ['privateKey' => $privateKey]);
    }

    /**
     * @param string $channel
     *
     * @return $this
     */
    public function subscribeChannel($channel)
    {
        $this->emitAndCheck('subscribe_channel', ['channel' => $channel]);

        return $this;
    }

    /**
     * @return $this
     */
    public function waitForNotification()
    {
        return $this->waitForMessage(['forwarded_notification']);
    }
}
