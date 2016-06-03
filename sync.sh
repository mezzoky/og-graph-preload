# set -ex

MAIN_PWD=`pwd`
PROJ='og-graph-preload'

RED='\033[0;31m'
NC='\033[0m' # No Color
mkdir -p ~/Projects/$PROJ
cd ~/Projects/$PROJ/
CURR_PWD=`pwd`

GIT_COMMIT=$1
if [[ -z $GIT_COMMIT ]]; then
    GIT_COMMIT="update repo"
fi

if [ "$MAIN_PWD" == "$CURR_PWD" ]; then
    printf "${RED}this is just only for sync from var/www/html to git local repo${NC}\n"
    exit 0
fi

ls | grep -v .git | xargs rm -rf
cp -R $MAIN_PWD/* ~/Projects/$PROJ/
chown -R $USER:$USER ~/Projects/$PROJ
git add .
git commit -m "$GIT_COMMIT"
git push origin master
