<?php

namespace qemu\hardware;

enum architecture {
    case aarch64;
    case alpha;
    case arm;
    case avr;
    case hppa;
    case i386;
    case loongarch64;
    case m68k;
    case microblaze;
    case microblazeel;
    case mips;
    case mips64;
    case mips64el;
    case mipsel;
    case or1k;
    case ppc;
    case ppc64;
    case riskv32;
    case riskv64;
    case rx;
    case s390x;
    case sh4;
    case sh4eb;
    case sparc;
    case sparc64;
    case tricore;
    case x86_64;
    case xtensa;
    case xtensaeb;
}