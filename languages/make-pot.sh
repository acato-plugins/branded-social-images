#!/usr/bin/env bash
cd "$(dirname "$0")"
msgfilter --keep-header -i bsi-nl_NL.po -o bsi.pot true
