#!/usr/bin/python2.5

import re
import os
import sys
from subprocess import check_call, Popen
import pwd
import grp

from common import *

check_dependencies()

setup_configuration()

def usage_and_exit():
    print "Usage: ./create-rootfs [SIZE-IN-MiB] [FILENAME] [MOUNT-POINT] [USER]:[GROUP]"
    sys.exit(1)

if len(sys.argv) != 5:
    usage_and_exit()

size_in_mib = int(sys.argv[1],10)
image_filename = sys.argv[2]
mount_point = sys.argv[3]
user_and_group = sys.argv[4]

m = re.search('^([a-zA-Z0-9]+):([a-zA-Z0-9]+)$',user_and_group)
if not m:
    print "The last argument must be of the form: username:groupname"
    usage_and_exit()

username = m.group(1)
groupname = m.group(2)

# These throw exceptions if either is unknown:
u = pwd.getpwnam(username)
g = grp.getgrnam(groupname)

if os.getuid() != 0:
    print "This script must be run as root, since it needs to mount the filesystem as a loopback device."
    sys.exit(2)

check_call(["dd",
            "if=/dev/zero",
            "of="+image_filename,
            "bs=1",
            "count=1",
            "seek="+str(size_in_mib)+"M"])
check_call(["chown",username+":"+groupname,image_filename])
check_call(["mke2fs","-F",image_filename])
check_call(["tune2fs","-j",image_filename])
check_call(["mount","-o","loop",image_filename,mount_point])
check_call(["debootstrap",
            "--include=apache2-mpm-prefork,openssh-server,git-core,cvs,emacs22-nox,vim,less,postgresql-8.3",
            "lenny",
            mount_point,
            "http://ftp.uk.debian.org/debian"])

root_ssh_directory = mount_point+"/root/.ssh"

check_call(["mkdir",root_ssh_directory])
check_call(["chmod","0700",root_ssh_directory])
check_call(["cp","id_dsa.root.pub",root_ssh_directory+"/authorized_keys"])

fp = open(mount_point+"/etc/apt/sources.list","w")
fp.write('''deb http://ftp.de.debian.org/debian/ lenny main
deb-src http://ftp.de.debian.org/debian/ lenny main''')
fp.close()

untemplate("etc-network-interfaces.template",mount_point+"/etc/network/interfaces")

fp = open(mount_point+"/etc/resolv.conf","w")
fp.write("nameserver "+configuration['GUEST_NAMESERVER'])
fp.close()

fp = open(mount_point+"/etc/hostname","w")
fp.write("sandbox")
fp.close()

check_call(["umount",mount_point])
