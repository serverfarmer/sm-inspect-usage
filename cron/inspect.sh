#!/bin/bash
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



out=/var/cache/farm
path=/etc/local/.farm

expand=$path/expand.json
inspect=$path/inspect.root

for server in `/opt/farm/ext/inspect-usage/utils/get-hosts.sh`; do

	if [[ $server =~ ^[a-z0-9.-]+$ ]]; then
		server="$server::"
	elif [[ $server =~ ^[a-z0-9.-]+[:][0-9]+$ ]]; then
		server="$server:"
	fi

	host=$(echo $server |cut -d: -f1)
	port=$(echo $server |cut -d: -f2)

	if [ "$port" = "" ]; then
		port=22
	fi

	sshkey=`/opt/farm/ext/keys/get-ssh-management-key.sh $host`
	ignore=`ignore_root $inspect $host`
	file=`create_json $out $host`

	/opt/farm/ext/inspect-usage/internal/usage.php $ignore $host $port root $sshkey $out/$file $expand $@ \
		|/opt/farm/ext/versioning/save.sh daily 20 $out $file &

done

ignore=`ignore_root $inspect $HOST`
file=`create_json $out $HOST`

/opt/farm/ext/inspect-usage/internal/usage.php $ignore localhost - - - $out/$file $expand $@ \
	|/opt/farm/ext/versioning/save.sh daily 20 $out $file
