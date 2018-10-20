#!/bin/sh

path=/etc/local/.farm
cat $path/virtual.hosts $path/physical.hosts $path/workstation.hosts |grep -vxFf $path/openvz.hosts |grep -v "^#"
