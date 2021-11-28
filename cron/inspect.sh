#!/bin/sh
. /opt/farm/scripts/init


create_json() {
	out=$1
	file=usage-$2.json

	if [ ! -f $out/$file ]; then
		echo "{}" >$out/$file
	fi

	echo $file
}

ignore_root() {
	inspect=$1
	host=$2

	if [ "`echo $host |grep -xFf $inspect`" != "" ]; then
		echo 0
	else
		echo 1
	fi
}


out=~/.serverfarmer/inspection
path=~/.serverfarmer/inventory

expand=$path/expand.json
inspect=$path/inspect.root

for server in `/opt/farm/mgr/inspect-usage/utils/get-hosts.sh`; do

	host=`/opt/farm/mgr/farm-manager/internal/decode.sh host $server`
	port=`/opt/farm/mgr/farm-manager/internal/decode.sh port $server`

	sshkey=`/opt/farm/ext/keys/get-ssh-management-key.sh $host`
	ignore=`ignore_root $inspect $host`
	file=`create_json $out $host`

	/opt/farm/mgr/inspect-usage/internal/usage.php $ignore $host $port root $sshkey $out/$file $expand $@ \
		|/opt/farm/ext/versioning/save.sh daily 20 $out $file &

done

ignore=`ignore_root $inspect $HOST`
file=`create_json $out $HOST`

/opt/farm/mgr/inspect-usage/internal/usage.php $ignore localhost - - - $out/$file $expand $@ \
	|/opt/farm/ext/versioning/save.sh daily 20 $out $file
