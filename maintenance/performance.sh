#!/bin/bash
START=0
# Determine number of available logical CPUs (can be overridden by argument or environment variable)
if [ -n "$1" ]; then
    END="$1"
elif [ -n "$END" ]; then
    END="$END"
else
    END=$(getconf _NPROCESSORS_ONLN 2>/dev/null)
    if ! [[ "$END" =~ ^[0-9]+$ ]] || [ "$END" -lt 1 ]; then
        END=1
    fi
fi
echo "Using $END parallel processes."

for ((i=START; i<END; i++)); do
    echo "Starting process: $i"
    (
	   # php ./MathPerformance.php benchmark $END $i --table=statistics --input=tex --hash=hash -v > log"$i".out &
	   php ./MathPerformance.php benchmark $END $i
       echo "Process $i finished."
    ) &
done

wait
echo "All processes have completed."
