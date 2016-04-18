[WIP]

# LocalWebhook

A simple tool to receive http/https webhook notifications for development purpose

This tool simplify web developments while using API using webhooks.

1) Install the server application at https://yourdomain.com

2) Configure a new webhook

- go to https://yourdomain.com/admin (enter login / password)
- Type a webhook path, ex : webhook_1
- store the generated key.

3) Install, configure and run the client:

- Install:
```bash
composer global require lucascherifi/local-webhook-client
```
- Configure:
```bash
local-webhook-client config webhook_1 # when prompted, enter the stored key, the local endpoint "http://localhost:8080/notifications"
```
- Run :
```bash
local-webhook-client webhook_1 run
```

The client will now redirect all notifications received at https://yourdomain.com/webhook_1 to http://localhost:8080/notifications
