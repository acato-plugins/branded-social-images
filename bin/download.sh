#!/usr/bin/env bash

cd "$(dirname "$0")"
CWD=$(pwd -P)

# windows???? nobody should be developing on windows! you should be in a Linux VM (or WSL) so you should be using the LINUX binaries.

SOURCE_URL='https://storage.googleapis.com/downloads.webmproject.org/releases/webp/'
MAC_NAME=libwebp-1.2.0-mac-10.15.tar.gz
MAC_SRC=${SOURCE_URL}${MAC_NAME}
LIN_NAME=libwebp-1.2.0-linux-x86-64.tar.gz
LIN_SRC=${SOURCE_URL}${LIN_NAME}

echo "Downloading MacOS version";

[ -d ./macos ] && rm -rf ./macos
wget $MAC_SRC || curl -o $MAC_NAME $MAC_SRC
mkdir ./macos
mv $MAC_NAME ./macos/
cd ./macos
tar -zxf $MAC_NAME

cd "$CWD"

echo "Downloading Linux version";

[ -d ./linux ] && rm -rf ./linux
wget $LIN_SRC || curl -o $LIN_NAME $LIN_SRC
mkdir ./linux
mv $LIN_NAME ./linux/
cd ./linux
tar -zxf $LIN_NAME

cd "$CWD"

echo "Removing old links";

# clean old links
[ -L cwebp ] && rm cwebp
[ -L dwebp ] && rm dwebp
[ -L cwebp-mac ] && rm cwebp-mac
[ -L dwebp-mac ] && rm dwebp-mac
[ -L cwebp-lin ] && rm cwebp-lin
[ -L dwebp-lin ] && rm dwebp-lin

echo "Creating new links";

# new links
ln -s macos/*/bin/cwebp cwebp-mac
ln -s macos/*/bin/dwebp dwebp-mac
ln -s linux/*/bin/cwebp cwebp-lin
ln -s linux/*/bin/dwebp dwebp-lin

echo "Creating platform dependant links";

[ "Darwin" = "$(uname)" ] && ln -s cwebp-mac cwebp && ln -s dwebp-mac dwebp
[ "Linux" = "$(uname)" ] && ln -s cwebp-lin cwebp && ln -s dwebp-lin dwebp

rm 'can-execute-binaries-from-php.*' 2>&1 >/dev/null

php ./test.php