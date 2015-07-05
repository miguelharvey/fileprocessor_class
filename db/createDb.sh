#!/bin/bash
db=$1

testing_data=`cat $2`

echo "drop database if exists ${db}; create database ${db};  use ${db};  $testing_data"  | cat | mysql -p
