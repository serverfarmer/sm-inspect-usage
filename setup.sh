#!/bin/sh

/opt/farm/scripts/setup/extension.sh sf-versioning
/opt/farm/scripts/setup/extension.sh sm-farm-manager
/opt/farm/scripts/setup/extension.sh sf-php

echo "setting up base directories and files"
mkdir -p ~/.serverfarmer/inspection ~/.serverfarmer/inventory
chmod 0710 ~/.serverfarmer ~/.serverfarmer/inspection
chown root:www-data ~/.serverfarmer ~/.serverfarmer/inspection

chmod 0700 ~/.serverfarmer/inventory
touch      ~/.serverfarmer/inventory/inspect.root

if [ ! -f ~/.serverfarmer/inventory/expand.json ]; then
	echo -n "{}" >~/.serverfarmer/inventory/expand.json
fi

if ! grep -q /opt/farm/mgr/inspect-usage/cron /etc/crontab; then
	echo "10 7 * * 1-6 root /opt/farm/mgr/inspect-usage/cron/inspect.sh" >>/etc/crontab
	echo "10 7 * * 7   root /opt/farm/mgr/inspect-usage/cron/inspect.sh --force" >>/etc/crontab
fi
