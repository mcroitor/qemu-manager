# QEMU Manager

The QEMU Manager is a simple front-end Web GUI for managing QEMU virtual machines. It is written in PHP and uses the QEMU command line tools to manage the virtual machines.

## Pre-requisites

- QEMU
- PHP

## Usage / Testing

You can up the Web server, but at the moment the authentication is not implemented.

For testing purposes, you can run the following command:

```bash
php -S localhost:8080 -t src
```

Then, open your browser and go to `http://localhost:8080`.
