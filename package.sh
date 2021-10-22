#!/usr/bin/env bash

cd "$(dirname "$0")"

[ "" = "$1" ] && echo "Usage: $0 version-number" && echo "Example: $0 1.0.4" && echo "Must match version number in readme.txt, info.json and wp-plugin.php" && exit 999

VERSION=$1

[ 1 -gt $(git status | grep 'nothing to commit, working tree clean' | wc -l) ] && echo "GIT checkout not clean, aborting" && exit 1;
[ 1 -gt $(cat wp-plugin.php | grep Version: | head -n 1 | grep $VERSION | wc -l) ] && echo "Version number given ($VERSION) does not match Version tag in wp-plugin.php" && exit 2;
[ 1 -gt $(cat info.json | grep '"Version":' | head -n 1 | grep $VERSION | wc -l) ] && echo "Version number given ($VERSION) does not match Version tag in info.json" && exit 3;
[ 1 -gt $(cat readme.txt | grep 'Stable tag:' | head -n 1 | grep $VERSION | wc -l) ] && echo "Version number given ($VERSION) does not match Stable tag in readme.txt" && exit 4;

SVN_PROJECT=https://plugins.svn.wordpress.org/branded-social-images
# SVN_PROJECT=svn://svn.clearsite.nl/wp_plugins/__test/branded-social-images
SVN_TRUNK=/trunk
SVN_TAGS=/tags
SVN_TAG=$SVN_TAGS/$VERSION

GIT_DIRECTORY="$(pwd -P)"

[ 1 -eq $(svn ls $SVN_PROJECT 2>&1 | grep " don't exist" | wc -l) ] && echo "SVN Project does not exist" && exit 5
[ 1 -eq $(svn ls $SVN_PROJECT$SVN_TRUNK 2>&1 | grep " don't exist" | wc -l) ] && echo "SVN Project Trunk does not exist" && exit 6
[ 1 -eq $(svn ls $SVN_PROJECT$SVN_TAGS 2>&1 | grep " don't exist" | wc -l) ] && echo "SVN Project Tags does not exist" && exit 7
[ 1 -gt $(svn ls $SVN_PROJECT$SVN_TAG 2>&1 | grep " don't exist" | wc -l) ] && echo "SVN Project Tags already exists. You cannot import the same tag" && exit 8

cd ~/Desktop

[ -d BSI_SVN_TRUNK ] && rm -rf ./BSI_SVN_TRUNK
svn checkout $SVN_PROJECT$SVN_TRUNK BSI_SVN_TRUNK
cd BSI_SVN_TRUNK

SVN_DIRECTORY="$(pwd -P)"

cp -a "$GIT_DIRECTORY"/* "$GIT_DIRECTORY"/.git "$SVN_DIRECTORY"/

# want these, but clean
for i in bin; do
	echo reverting bin to git state. this removes downloaded stuff.
	rm -rf "$SVN_DIRECTORY"/$i
	git checkout $i
	echo done.
done

# don't want these
for i in node_modules assets composer.json composer.lock package.sh tmp .git package.json package-lock.json gulpfile.js languages/make-pot.sh; do
	rm -rf "$SVN_DIRECTORY"/$i
done
find . -name '.DS_Store' -exec rm {} \;

# added files
svn status | grep '?' | awk '{print $2}' | xargs svn add
# removed or conflicted, how to proceed here?
svn status | grep '!' | awk '{print $2}' | xargs svn rm
# remains are modified
svn commit -m "Import changes from GitHub for version $VERSION"
svn cp $SVN_PROJECT$SVN_TRUNK $SVN_PROJECT$SVN_TAG -m "version $VERSION"
