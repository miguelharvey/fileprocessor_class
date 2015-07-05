#!/bin/bash
db=$1
echo "drop database if exists $db;" | cat | mysql -p
 