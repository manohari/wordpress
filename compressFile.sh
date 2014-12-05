#!/bin/bash
array=( $@ )
len=${#array[@]}
filename=${array[$len-1]}
fileList=${array[@]:0:$len-1}
for f in $fileList;
do
tar -rf $filename $f
done
gzip $filename
