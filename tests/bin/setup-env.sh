#!/bin/sh

set -ex

basedir="${0%/*}/.."

version=latest
appCodePath="${basedir}/../../../vip-go-skeleton"
defaultImage=demo

while getopts v:p:c: flag
do
    case "${flag}" in
        v) version=${OPTARG};;
        c) defaultImage=${OPTARG};;
        *) echo "WARNING: Unexpected option ${flag}";;
    esac
done

if [ -z "${version}" ]; then
    version=${WORDPRESS_VERSION:-latest}
fi

# Destroy existing test site
vip dev-env destroy --slug=e2e-test-site || true

# Create and run test site
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --mu-plugins="${defaultImage}" --mailpit false --wordpress=trunk --multisite=false --app-code="${appCodePath}" --php 8.0 --xdebug false --phpmyadmin false --elasticsearch true < /dev/null
vip dev-env start --slug e2e-test-site --skip-wp-versions-check
vip dev-env exec --slug e2e-test-site --quiet -- wp plugin activate vip-governance
vip dev-env exec --slug e2e-test-site --quiet -- wp core update --force --version="${version}"