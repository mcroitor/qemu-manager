<?php

namespace qemu\hardware;

enum disk {
    case raw;
    case qcow2;
    case qed;
    case qcow;
    case luks;
    case vdi;
    case vmdk;
    case vpc;
    case vhdx;
}