#!/bin/sh

path=~/.farm
cat $path/virtual.hosts $path/physical.hosts $path/cloud.hosts $path/container.hosts $path/workstation.hosts |grep -v "^#"
