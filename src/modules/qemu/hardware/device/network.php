<?php

namespace qemu\hardware;

enum network: string {
    case e1000 = "e1000";
    case e1000_82544gc = "e1000-82544gc";
    case e1000_82545em = "e1000-82545em";
    case e1000c = "e1000c";
    case i82550 = "i82550";
    case i82551 = "i82551";
    case i82557a = "i82557a";
    case i82557b = "i82557b";
    case i82557c = "i82557c";
    case i82558a = "i82558a";
    case i82558b = "i82558b";
    case i82559a = "i82559a";
    case i82559b = "i82559b";
    case i82559c = "i82559c";
    case i82559er = "i82559er";
    case i82562 = "i82562";
    case i82801 = "i82801";
    case igb = "igb";
    case ne2k_pci = "ne2k_pci";
    case ne2k_isa = "ne2k_isa";
    case pcnet = "pcnet";
    case rocker = "rocker";
    case rtl8139 = "rtl8139";
    case tulip = "tulip";
    case usb_net = "usb-net";
    case virtio_net_device = "virtio-net-device";
    case virtio_net_pci = "virtio-net-pci";
    case virtio_net_pci_non_transitional = "virtio-net-pci-non-transitional";
    case virtio_net_pci_transitional = "virtio-net-pci-transitional";
    case vmxnet3 = "vmxnet3";
}