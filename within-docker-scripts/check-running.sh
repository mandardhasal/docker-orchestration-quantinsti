#!/bin/bash

# Start the first process
./start-jupytor.sh -D
status=$?
if [ $status -ne 0 ]; then
  echo "Failed to start my_first_process: $status"
  exit $status
fi

while sleep 60; do
  ps aux | grep my_first_process |grep -q -v grep
  PROCESS_1_STATUS=$?

  if [ $PROCESS_1_STATUS -ne 0 ]; then
    ./start-jupytor.sh
    #exit 1
  fi
done