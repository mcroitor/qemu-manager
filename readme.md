# QEMU Manager

The QEMU Manager is a simple front-end Web GUI for managing QEMU virtual machines. It is written in PHP and uses the QEMU command line tools to manage the virtual machines.

## Pre-requisites

- QEMU
- PHP

## Usage / Testing

Start the built-in PHP web server:

```bash
php -S localhost:8080 -t src
```

Then open:

- `http://localhost:8080`

## First Launch (Create First Administrator)

Authentication is enabled. On a fresh setup, create the first administrator account before using protected modules.

1. Open `http://localhost:8080/?q=auth/bootstrap-admin`
2. Fill in username, email, and password
3. Submit the form to create the first `admin` account
4. After successful creation, you will be logged in automatically

After bootstrap is complete:

- Login page: `http://localhost:8080/?q=auth/login`
- Register page (viewer role): `http://localhost:8080/?q=auth/register`

Protected areas such as Virtual Machines and Network Settings require at least `operator` role.
