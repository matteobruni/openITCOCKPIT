#!/bin/bash

if ! [ $(id -u) = 0 ]; then
    echo "You need to run this script as root user or via sudo!"
    exit 1
fi

APPDIR="/opt/openitc/frontend"

OLDINIFILE=/etc/openitcockpit/mysql.cnf
if [[ ! -f "$OLDINIFILE" ]]; then
    echo "Error: Could not find V3 database configuration $OLDINIFILE"
    exit 1;
fi

chmod +x $APPDIR/bin/scripts/pre_v4_upgrade.php
$APPDIR/bin/scripts/pre_v4_upgrade.php

INIFILE=/opt/openitc/etc/mysql/mysql.cnf
DUMPINIFILE=/opt/openitc/etc/mysql/dump.cnf
BASHCONF=/opt/openitc/etc/mysql/bash.conf

DEBIANCNF=/etc/mysql/debian.cnf

MYSQL_USER=$(php -r "echo parse_ini_file('/etc/openitcockpit/mysql.cnf')['user'];")
MYSQL_DATABASE=$(php -r "echo parse_ini_file('/etc/openitcockpit/mysql.cnf')['database'];")
MYSQL_PASSWORD=$(awk '$1 == "password" { print }' "/etc/openitcockpit/mysql.cnf" |cut -d= -f2 | sed 's/^\s*//' | sed 's/\s*$//' | sed 's_/_\\/_g')

PHPVersion=$(php -r "echo substr(PHP_VERSION, 0, 3);")

OSVERSION=$(grep VERSION_CODENAME /etc/os-release | cut -d= -f2)

echo "Copy required system files"
cp -r ${APPDIR}/system/etc/. /etc/
cp -r ${APPDIR}/system/lib/. /lib/
cp -r ${APPDIR}/system/fpm/. /etc/php/${PHPVersion}/fpm/
cp -r ${APPDIR}/system/usr/. /usr/
cp ${APPDIR}/system/nginx/ssl_options_$OSVERSION /etc/nginx/openitc/ssl_options.conf
cp ${APPDIR}/system/nginx/ssl_cert.conf /etc/nginx/openitc/ssl_cert.conf
echo "# This file will NOT be overwritten during an update" >> /etc/nginx/openitc/custom.conf
chmod +x /usr/bin/oitc

echo "Create required system folders"
mkdir -p /opt/openitc/etc/{mysql,grafana,carbon,frontend,nagios,phpnsta,statusengine} /opt/openitc/etc/statusengine/Config

mkdir -p /opt/openitc/logs/frontend/nagios
chown www-data:www-data /opt/openitc/logs/frontend
chown nagios:nagios /opt/openitc/logs/frontend/nagios
chmod 775 /opt/openitc/logs/frontend
chmod 775 /opt/openitc/logs/frontend/nagios

mkdir -p /opt/openitc/frontend/tmp/nagios
chown www-data:www-data /opt/openitc/frontend/tmp
chown nagios:nagios /opt/openitc/frontend/tmp/nagios

mkdir -p /opt/openitc/frontend/webroot/img/charts
chown www-data:www-data /opt/openitc/frontend/webroot/img/charts

if [[ -d /opt/openitc/frontend/plugins/MapModule/webroot/img/ ]]; then
    chown -R www-data:www-data /opt/openitc/frontend/plugins/MapModule/webroot/img/
fi

#Copy V3 MapModule images into V4 MapModule
if [[ -d /usr/share/openitcockpit/app/Plugin/MapModule/webroot/img/ ]]; then
    mkdir -p /opt/openitc/frontend/plugins/MapModule/webroot/img/

    cp -r /usr/share/openitcockpit/app/Plugin/MapModule/webroot/img/. /opt/openitc/frontend/plugins/MapModule/webroot/img/
    chown -R www-data:www-data /opt/openitc/frontend/plugins/MapModule/webroot/img/
fi

mkdir -p /opt/openitc/frontend/webroot/img/userimages/
if [[ -d /usr/share/openitcockpit/app/webroot/userimages/ ]]; then
    cp -r /usr/share/openitcockpit/app/webroot/userimages/. /opt/openitc/frontend/webroot/img/userimages/
    chown -R www-data:www-data /opt/openitc/frontend/webroot/img/userimages
fi

echo "Clear all gearman queues"
systemctl stop gearman-job-server.service
systemctl start gearman-job-server.service

echo "Enable new systemd services"
systemctl daemon-reload
systemctl enable\
 sudo_server.service\
 oitc_cmd.service\
 gearman_worker.service\
 push_notification.service\
 nodejs_server.service\
 openitcockpit-graphing.service\
 oitc_cronjobs.timer

if [[ ! -f "$INIFILE" ]]; then
    echo "Create local MySQL configuration and database"

    if [[ -f "$DEBIANCNF" ]]; then
        echo "Detected Debian based distribution"

        # set sql_mode to enable group by like it was in the good old times :)
        mysql --defaults-extra-file=${DEBIANCNF} -e "SET GLOBAL sql_mode = '';"

        mysql --defaults-extra-file=${DEBIANCNF} -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;" -B
        mysql --defaults-extra-file=${DEBIANCNF} -e "GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_DATABASE}'@'localhost';" -B

        echo "; Automatically generated for openITCOCKPIT scripts. DO NOT TOUCH!" >$INIFILE
        echo "[client]" >>$INIFILE
        echo "database = ${MYSQL_DATABASE}" >>$INIFILE
        echo "host = localhost" >>$INIFILE
        echo "user = ${MYSQL_USER}" >>$INIFILE
        echo "password = ${MYSQL_PASSWORD}" >>$INIFILE
        echo "port = 3306" >>$INIFILE

        echo "; Automatically generated for mysqldump. DO NOT TOUCH!" >$DUMPINIFILE
        echo "[client]" >>$DUMPINIFILE
        echo "host     = localhost" >>$DUMPINIFILE
        echo "user     = ${MYSQL_USER}" >>$DUMPINIFILE
        echo "password = ${MYSQL_PASSWORD}" >>$DUMPINIFILE
        echo "port     = 3306" >>$DUMPINIFILE

        echo "dbc_dbuser='${MYSQL_USER}'" >$BASHCONF
        echo "dbc_dbpass='${MYSQL_PASSWORD}'" >>$BASHCONF
        echo "dbc_dbserver='127.0.0.1'" >>$BASHCONF
        echo "dbc_dbport='3306'" >>$BASHCONF
        echo "dbc_dbname='${MYSQL_DATABASE}'" >>$BASHCONF

        chown www-data:nagios $INIFILE
        chmod 660 $INIFILE

        chown www-data:nagios $DUMPINIFILE
        chmod 660 $DUMPINIFILE

        chown www-data:nagios $BASHCONF
        chmod 660 $BASHCONF

    else
        echo "Unsupported distribution or $DEBIANCNF is missing!"
        exit 1
    fi

fi

echo "---------------------------------------------------------------"
echo "Import openITCOCKPIT Core database schema"
oitc migrations migrate


echo "---------------------------------------------------------------"
echo "Load default database"
oitc migrations seed

echo "Running openITCOCKPIT Module database migration/s"
for PLUGIN in $(ls -1 "${APPDIR}/plugins"); do
    if [[ "$PLUGIN" == *Module ]]; then
        if [[ -d "${APPDIR}/plugins/${PLUGIN}/config/Migrations" ]]; then
            echo "Running openITCOCKPIT ${PLUGIN} database migration"
            oitc migrations migrate -p "${PLUGIN}"
        fi

        if [[ -d "${APPDIR}/plugins/${PLUGIN}/config/Seeds" ]]; then
            num_files=$(find "${APPDIR}/plugins/${PLUGIN}/config/Seeds" -mindepth 1 -iname "*.php" -type f | wc -l)
            if [[ "$num_files" -gt 0 ]]; then
                echo "Importing default records for ${PLUGIN} into database"
                oitc migrations seed -p "${PLUGIN}"
            fi
        fi

    fi
done

echo "---------------------------------------------------------------"
echo "Create new WebSocket Key"
WEBSOCKET_KEY=$(php -r "echo bin2hex(openssl_random_pseudo_bytes(80, \$cstrong));")
mysql "--defaults-extra-file=${INIFILE}" -e "UPDATE systemsettings SET \`systemsettings\`.\`value\`='${WEBSOCKET_KEY}' WHERE \`key\`='SUDO_SERVER.API_KEY';"

if [ ! -f /opt/openitc/etc/grafana/admin_password ]; then
    echo "Generate new Grafana password for user 'admin'"
    pwgen 10 1 > /opt/openitc/etc/grafana/admin_password
fi

STATUSENGINE_VERSION=Statusengine3

if [[ $STATUSENGINE_VERSION == "Statusengine2" ]]; then
    mysql "--defaults-extra-file=${INIFILE}" -e "INSERT INTO \`configuration_files\` (\`config_file\`, \`key\`, \`value\`)VALUES('DbBackend', 'dbbackend', 'Nagios');"
    echo "<?php return ['dbbackend' => 'Nagios',];" > /opt/openitc/frontend/config/dbbackend.php
fi

if [[ $STATUSENGINE_VERSION == "Statusengine3" ]]; then
    mysql "--defaults-extra-file=${INIFILE}" -e "INSERT INTO \`configuration_files\` (\`config_file\`, \`key\`, \`value\`)VALUES('DbBackend', 'dbbackend', 'Statusengine3');"
    echo "<?php return ['dbbackend' => 'Statusengine3',];" > /opt/openitc/frontend/config/dbbackend.php
fi

oitc config_generator_shell --generate

echo "---------------------------------------------------------------"
echo "Load Statusengine DB Schema"
if [[ $STATUSENGINE_VERSION == "Statusengine2" ]]; then
    echo "Setup Statusengine 2"

    cp -r ${APPDIR}/system/Statusengine2/legacy_schema_innodb.php /opt/openitc/statusengine2/cakephp/app/Plugin/Legacy/Config/Schema/legacy_schema_innodb.php
    cp -r ${APPDIR}/system/Statusengine2/database.php /opt/openitc/statusengine2/cakephp/app/Config/database.php
    chmod +x /opt/openitc/statusengine2/cakephp/app/Console/cake
    /opt/openitc/statusengine2/cakephp/app/Console/cake schema update --plugin Legacy --file legacy_schema_innodb.php --connection legacy --yes
fi

if [[ $STATUSENGINE_VERSION == "Statusengine3" ]]; then
    echo "Setup Statusengine 3"

    cp -r ${APPDIR}/system/Statusengine3/mysql.php /opt/openitc/statusengine3/worker/lib/mysql.php

    chmod +x /opt/openitc/statusengine3/worker/bin/Console.php
    chmod +x /opt/openitc/statusengine3/worker/bin/StatusengineWorker.php

    /opt/openitc/statusengine3/worker/bin/Console.php database --update

    mysql --defaults-extra-file=${INIFILE} < ${APPDIR}/partitions_statusengine3.sql
fi

echo "---------------------------------------------------------------"
echo "Configure Grafana"

# Copy V3 Grafana Configuration
cp /etc/openitcockpit/grafana/admin_password /opt/openitc/etc/grafana/admin_password
cp /etc/openitcockpit/grafana/api_key /opt/openitc/etc/grafana/api_key

systemctl restart openitcockpit-graphing.service

ADMIN_PASSWORD=$(cat /opt/openitc/etc/grafana/admin_password)

if [ ! -f /opt/openitc/etc/grafana/api_key ]; then
    echo "Create new Grafana API Key for openITCOCKPIT"
    COUNTER=0

    set +e
    while [ "$COUNTER" -lt 30 ]; do
        echo "Try to connect to Grafana API..."
        #Is Grafana Server Online?
        STATUSCODE=$(NO_PROXY="127.0.0.1" curl 'http://127.0.0.1:3033/api/admin/stats' -XGET -uadmin:$ADMIN_PASSWORD -H 'Content-Type: application/json' -I 2>/dev/null | head -n 1 | cut -d$' ' -f2)

        if [ "$STATUSCODE" == "200" ]; then
            API_KEY=$(NO_PROXY="127.0.0.1" curl 'http://127.0.0.1:3033/api/auth/keys' -XPOST -uadmin:$ADMIN_PASSWORD -H 'Content-Type: application/json' -d '{"role":"Editor","name":"openITCOCKPIT"}' | jq -r '.key')
            echo "$API_KEY" >/opt/openitc/etc/grafana/api_key
            break
        fi
        COUNTER=$((COUNTER + 1))
        sleep 1
    done

    if [ ! -f /opt/openitc/etc/grafana/api_key ]; then
        echo "ERROR!"
        echo "Could not create API key for Grafana"
    fi
    set -e
fi

echo "Check if Graphite Datasource exists in Grafana"
DS_STATUSCODE=$(NO_PROXY="127.0.0.1" curl 'http://127.0.0.1:3033/api/datasources/name/Graphite' -XGET -uadmin:$ADMIN_PASSWORD -H 'Content-Type: application/json' -I 2>/dev/null | head -n 1 | cut -d$' ' -f2)
if [ "$DS_STATUSCODE" == "404" ]; then
    echo "Create Graphite as default Datasource for Grafana"
    export NO_PROXY="127.0.0.1"
    RESPONSE=$(NO_PROXY="127.0.0.1" curl 'http://127.0.0.1:3033/api/datasources' -XPOST -uadmin:$ADMIN_PASSWORD -H 'Content-Type: application/json' -d '{
      "name":"Graphite",
      "type":"graphite",
      "url":"http://graphite-web:8080",
      "access":"proxy",
      "basicAuth":false,
      "isDefault": true,
      "jsonData": {
        "graphiteVersion": 1.1
      }
    }')
    echo $RESPONSE | jq .
fi
echo "Ok: Graphite datasource exists."

if [ -f /opt/openitc/etc/grafana/api_key ]; then
    echo "Check for Grafana Configuration in openITCOCKPIT database"
    API_KEY=$(cat /opt/openitc/etc/grafana/api_key)
    set +e
    COUNT=$(mysql "--defaults-extra-file=$INIFILE" -e "SELECT COUNT(*) FROM grafana_configurations;" -B -s 2>/dev/null)
    if [ "$?" == 0 ] && [ "$COUNT" == 0 ]; then
        echo "Create missing default configuration."
        mysql --defaults-extra-file=${INIFILE} -e "INSERT INTO grafana_configurations (api_url, api_key, graphite_prefix, use_https, use_proxy, ignore_ssl_certificate, dashboard_style, created, modified) VALUES('grafana.docker', '${API_KEY}', 'openitcockpit', 1, 0, 1, 'light', '2018-12-05 08:42:55', '2018-12-05 08:42:55');"
    fi
    set -e
fi

oitc compress

# Migrate data from NDO schema to Statusengine 3

echo "Run MySQL migration"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE aros SET model='Usergroups';"

mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='F j, Y H:i:s' WHERE dateformat='%B %e, %Y %H:%M:%S';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='m-d-Y H:i:s' WHERE dateformat='%m-%d-%Y  %H:%M:%S';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='m-d-Y H:i' WHERE dateformat='%m-%d-%Y  %H:%M';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='m-d-Y h:i:s A' WHERE dateformat='%m-%d-%Y  %l:%M:%S %p';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='H:i:s m-d-Y' WHERE dateformat='%H:%M:%S  %m-%d-%Y';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='j F Y, H:i:s' WHERE dateformat='%e %B %Y, %H:%M:%S';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='d.m.Y - H:i:s' WHERE dateformat='%d.%m.%Y - %H:%M:%S';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='d.m.Y - h:i:s A' WHERE dateformat='%d.%m.%Y - %l:%M:%S %p';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='H:i:s - d.m.Y' WHERE dateformat='%H:%M:%S - %d.%m.%Y';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='H:i - d.m.Y' WHERE dateformat='%H:%M - %d.%m.%Y';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='Y-m-d H:i' WHERE dateformat='%Y-%m-%d %H:%M';"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE users SET dateformat='Y-m-d H:i:s' WHERE dateformat='%Y-%m-%d %H:%M:%S';"

mysql --defaults-extra-file=${INIFILE} -e "UPDATE \`systemsettings\` SET \`value\`='session' WHERE \`key\`='FRONTEND.AUTH_METHOD' AND \`value\`='twofactor';"

mysql --defaults-extra-file=${INIFILE} -e "TRUNCATE TABLE changelogs;"
mysql --defaults-extra-file=${INIFILE} -e "TRUNCATE TABLE changelogs_to_containers;"

mysql --defaults-extra-file=${INIFILE} -e "UPDATE commands SET command_line = REPLACE(command_line, '/usr/share/openitcockpit/app/Console/cake', '/opt/openitc/frontend/bin/cake');"

mysql --defaults-extra-file=${INIFILE} -e "UPDATE widgets SET icon = REPLACE(icon, 'fa-', 'fas fa-');"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE widgets SET icon = REPLACE(icon, 'fa-exchange', 'fa-exchange-alt');"
mysql --defaults-extra-file=${INIFILE} -e "UPDATE widgets SET icon = REPLACE(icon, 'fa-pencil-square-o', 'fa-pencil-square');"

#ALC dependencies config for itc core
echo "---------------------------------------------------------------"
echo "Scan for new user permissions. This will take a while..."
oitc Acl.acl_extras aco_sync

#Set default permissions, check for always allowed permissions and dependencies
oitc roles --enable-defaults --admin

echo "---------------------------------------------------------------"
echo "Flush redis cache"
redis-cli FLUSHALL

oitc update3_to4 --email-config
oitc update3_to4 --activate-users

oitc update3_to4 --migrate-notifications
oitc update3_to4 --migrate-statehistory
oitc update3_to4 --migrate-acknowledgements
oitc update3_to4 --migrate-downtimes

echo "---------------------------------------------------------------"
echo "Convert MySQL Tables from utf8_swedish_ci to utf8mb4_general_ci..."

mysql --defaults-extra-file=${INIFILE} -e "ALTER DATABASE ${MYSQL_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

mysql --defaults-extra-file=${INIFILE} --batch --skip-column-names -e "SELECT TABLE_NAME FROM \`information_schema\`.\`TABLES\` WHERE \`TABLE_SCHEMA\`='${MYSQL_DATABASE}' AND \`TABLE_NAME\` NOT LIKE 'nagios_%' AND \`TABLE_NAME\` NOT LIKE 'statusengine_%';" | while read TABLE_NAME; do
    echo "ALTER TABLE \`${TABLE_NAME}\` CONVERT TO CHARACTER SET utf8mb4; ✔"
    mysql --defaults-extra-file=${INIFILE} -e "ALTER TABLE \`${TABLE_NAME}\` CONVERT TO CHARACTER SET utf8mb4;"
done

#oitc setup

oitc nagios_export

echo "Enabling webserver configuration"
rm -rf /etc/nginx/sites-enabled/openitc
ln -s /etc/nginx/sites-available/openitc /etc/nginx/sites-enabled/openitc
rm -f /etc/nginx/sites-enabled/default

systemctl restart\
 statusengine.service\
 nagios.service\
 nginx.service\
 sudo_server.service\
 oitc_cmd.service\
 gearman_worker.service\
 push_notification.service\
 nodejs_server.service\
 oitc_cronjobs.timer

for srv in supervisor.service; do
  if systemctl is-active --quiet $srv; then
    systemctl restart $srv
  fi
done

echo "Detected PHP Version: ${PHPVersion} try to restart php-fpm"

systemctl is-enabled --quiet php${PHPVersion}-fpm.service
RC=$?
if [ $RC -eq 0 ]; then
    #Is it php7.3-fpm-service ?
    systemctl restart php${PHPVersion}-fpm.service
else
    # Is it just php-fpm.service?
    systemctl is-enabled --quiet php-fpm.service
    RC=$?
    if [ $RC -eq 0 ]; then
        systemctl restart php-fpm.service
    else
        echo "ERROR: could not detect php-fpm systemd service file. You need to restart php-fpm manualy"
    fi
fi

BACKUP_BASEDIR=/var/backups/openitcockpit_v3
echo "Move openITCOCKPIT V3 Files to ${BACKUP_BASEDIR}"

mkdir -p "${BACKUP_BASEDIR}/usr/share"
mkdir -p "${BACKUP_BASEDIR}/etc"
mkdir -p "${BACKUP_BASEDIR}/var/lib"

rsync -ahP /usr/share/openitcockpit "${BACKUP_BASEDIR}/usr/share/"
rsync -ahP /etc/openitcockpit "${BACKUP_BASEDIR}/etc/"
rsync -ahP /var/lib/openitcockpit "${BACKUP_BASEDIR}/var/lib"

rm -rf /usr/share/openitcockpit
rm -rf /etc/openitcockpit
rm -rf /var/lib/openitcockpit

echo ""
echo ""
echo "Upgrade to openITCOCKPIT 4 done."
echo "######################################"
echo "# Please execute                     #"
echo "# oitc reset_password --print        #"
echo "# to reset your users password.      #"
echo "######################################"
echo ""

date > /opt/openitc/etc/.installation_done
