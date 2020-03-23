#!/bin/sh

if grep -q /opt/farm/mgr/inspect-usage/cron /etc/crontab; then
	sed -i -e "/\/opt\/farm\/mgr\/inspect-usage\/cron/d" /etc/crontab
fi
