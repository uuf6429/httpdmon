Access / Error Log Monitor
======

Introduction
------------

[![Build Status](https://scrutinizer-ci.com/g/uuf6429/httpdmon/badges/build.png?b=master)](https://scrutinizer-ci.com/g/uuf6429/httpdmon/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/uuf6429/httpdmon/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/uuf6429/httpdmon/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/uuf6429/httpdmon/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/uuf6429/httpdmon/?branch=master)

httpdmon is a *command-line utility* based on PHP that monitors and prints information parsed from multiple Apache logfiles in (almost) realtime.

Requirements
------------

This software has been tested and guaranteed to work on PHP 5.3+ on a Linux (CentOS) system. In the future, a Windows version might be available.

Note that for the console colors to work on Windows, [ansicon](https://github.com/adoxa/ansicon) is required.

**Important:** The default logfile paths might not be the same on your system, in which case edit the script file accordingly.

Installation
------------

You can use the following shell-script to install/update this script:

    sudo su
    F=/usr/bin/httpdmon
    B=https://raw.github.com/uuf6429/httpdmon/master/
    rm -f $F
    rm -rf ${F}.d
    wget -O $F ${B}build/httpdmon.php
    echo '#!/usr/bin/php -q'|cat - $F > /tmp/out && mv -f /tmp/out $F
    chmod +x $F

To download access/error log definitions, run the following command for the desired file (replace `$NAME` with the definition file name):

    mkdir -p ${F}.d
    D=$NAME
    wget -O ${F}.d/$D ${B}httpdmon.d/$D

After running the above commands, you can launch the utility any time just by typing `httpdmon` on the command line.

Screenshot(s)
-------------

See it in action...

![Imgur Screenshot](http://i.imgur.com/tNZU1rZ.png)
