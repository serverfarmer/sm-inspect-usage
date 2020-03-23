#!/bin/sh

/opt/farm/scripts/setup/extension.sh sf-versioning
/opt/farm/scripts/setup/extension.sh sf-farm-manager
/opt/farm/scripts/setup/extension.sh sf-php

echo "setting up base directories and files"
mkdir -p   /var/cache/farm /etc/local/.farm
chmod 0710 /var/cache/farm
chown root:www-data /var/cache/farm

chmod 0700 /etc/local/.farm
touch      /etc/local/.farm/inspect.root

if [ ! -f /etc/local/.farm/expand.json ]; then
	echo -n "{}" >/etc/local/.farm/expand.json
fi

if ! grep -q /opt/farm/mgr/inspect-usage/cron /etc/crontab; then
	echo "10 7 * * 1-6 root /opt/farm/mgr/inspect-usage/cron/inspect.sh" >>/etc/crontab
	echo "10 7 * * 7   root /opt/farm/mgr/inspect-usage/cron/inspect.sh --force" >>/etc/crontab
fi
