#!/bin/sh
i=0
while [ $i -le 28 ]
do
  j=`expr $i + 1`
  echo $i
  php ReRenderMath.php  ${i}000 ${j}000 -f --conf /home/wiki/LocalSettings.php>$i&
  i=$j
done