README
======

Introduction
------------

httpdmon is a *command-line utility* based on PHP that monitors and prints information parsed from multiple Apache logfiles in (almost) realtime.

Requirements
------------

This software has been tested and guaranteed to work on PHP 5.3+ on a Linux (CentOS) system. In the future, a Windows version might be available.

**Important:** The default logfile paths might not be the same on your system, in which case edit the script file accordingly.

Installation
------------

You can use the following shell-script to install/update this script:

    F=/usr/bin/httpdmon
    U=https://raw.github.com/uuf6429/httpdmon/master/httpdmon.php
    
    sudo su
    rm -f $F
    wget $U -O $F
    echo '#!/usr/bin/php'|cat - $F > /tmp/out && mv -f /tmp/out $F
    chmod +x $F

After running the above commands, you can launch the utility any time just by typing `httpdmon` on the command line.

Screenshot(s)
-------------

See it in action...

![Imgur Screenshot](http://i.imgur.com/tNZU1rZ.png)