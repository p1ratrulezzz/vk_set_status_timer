#!/bin/bash

echo "${VK_TOKEN}" > token.txt
php set_status.php
rm -f token.txt
