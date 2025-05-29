#!/usr/bin/env bash

cd "$(dirname "$0")"/..

wp i18n make-pot . languages/bsi.pot --exclude=vendor,tests,bin,build,assets,dist,node_modules,composer.lock,composer.json --domain=bsi --skip-js
wp i18n update-po languages/bsi.pot languages/
wp i18n make-mo languages/
wp i18n make-php languages/
