<?php

namespace qemu\hardware;

enum accelerator {
    case tcg;
    case kvm;
    case xen;
    case hax;
    case hvf;
    case whpx;
}