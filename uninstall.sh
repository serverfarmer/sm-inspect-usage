#!/bin/sh

if grep -q /opt/farm/ext/inspect-usage/cron /etc/crontab; then
	sed -i -e "/\/opt\/farm\/ext\/inspect-usage\/cron/d" /etc/crontab
fi
