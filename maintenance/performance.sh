#!/bin/bash
START=0
END=1 #$(nproc)
for ((i=START; i<END; i++))
do
   echo "Starting process: $i"
   # php ./MathPerformance.php benchmark $END $i --table=statistics --input=tex --hash=hash -v > log"$i".out &
   php ./MathPerformance.php benchmark $END $i &
done