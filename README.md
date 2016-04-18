[WIP]

# LocalWebhook

A simple tool to receive http/https webhook notifications for development purpose

This tool simplify web developments while using API using webhooks.

1) Install the server: https://yourdomain.com/the_webhook_a

2) Install and run the client:

```bash
composer global require lucascherifi/local-webhook-client

local-webhook-client the_webhook_a http://localhost:8080/notifications

```

3) Configure the API to send notifications to https://yourdomain.com/the_webhook_a.

The client will send the notification to http://localhost:8080/notifications
