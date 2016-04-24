Client installation guide
=========================

## Composer

If you have already set up a global install of Composer just run:

```bash
$ composer global require lucascherifi/localhook-client
```

Be aware that in order for Localhook to be awesome it will install a good amount of other dependencies. If you rather have it self-contained, use the Phar method just below.

## Phar

Alternatively, you can download `localhook.phar`:

```bash
$ curl -OS https://.../localhook.phar
```

Please note that as Github is using a DDOS protection system, if using CURL fails, just manually download the phar file.

If you want to run localhook instead of php localhook.phar, move it to /usr/local/bin:

```bash
$ chmod +x localhook.phar
$ sudo mv localhook.phar /usr/local/bin/localhook
```

Please note that you need to have the phar extension installed to use this method. It should be installed by default on most OSes.
