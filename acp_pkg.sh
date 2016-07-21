

base="d8a"
repo="repo/zw"
now=`date "+%Y%m%d-%H%M%S"`

cd $repo
git checkout master && git pull && git branch $now && git checkout $now
git rm -rf docroot/* && rm -rf docroot/*
ln -s docroot web
mkdir -p config/default
touch config/default/.gitkeep
cd ..

#
cp -R $base/web/ $repo/docroot/
mkdir -p $repo/vendor/
cp -R $base/vendor/ $repo/vendor/

# remove any .git folders than may have snuck in
find $repo/docroot/ -name .git | xargs rm -rf

cd $repo
git add *
git commit -m "rebuild $now"
git push --set-upstream origin $now
