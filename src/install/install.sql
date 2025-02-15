CREATE TABLE settings (
    name TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL
);

INSERT INTO 
    settings (name, value) 
VALUES
    ('app-version', '1.0.0'),
    ('app-qemu-path', ''),
    ('site-name', 'QEMU Manager');

CREATE TABLE virtual_machine (
    name TEXT NOT NULL UNIQUE,
    platform TEXT NOT NULL,
    hda TEXT DEFAULT NULL,
    hdb TEXT DEFAULT NULL,
    cdrom TEXT DEFAULT NULL,
    memory INTEGER DEFAULT 512,
    cpu INTEGER DEFAULT 1,
    boot TEXT DEFAULT 'c'
);

CREATE TABLE network_interface (
    machine_name TEXT NOT NULL UNIQUE,
    mac TEXT NOT NULL UNIQUE,
    ip TEXT DEFAULT NULL,
    netmask TEXT DEFAULT NULL,
    gateway TEXT DEFAULT NULL,
    dns TEXT DEFAULT NULL
);

CREATE TABLE port_forwarding (
    machine_name TEXT NOT NULL UNIQUE,
    protocol TEXT NOT NULL,
    host_port INTEGER NOT NULL,
    guest_port INTEGER NOT NULL,
    guest_ip TEXT DEFAULT NULL
);
