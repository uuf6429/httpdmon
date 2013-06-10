=== Introduction ===

httpdmon is a *command-line utility* based on PHP that monitors and prints info about multiple Apache logfiles in realtime.

=== Installation ===

You can use the following shell-script to install/update this script:

    F=/usr/bin/httpdmon
    U=githuburl
    
    sudo su
    rm -f $F
    wget $U -O $F
    echo '#!/usr/bin/php'|cat - $F > /tmp/out && mv -f /tmp/out $F
    chmod +x $F

After running the above commands, you can launch the utility any time just by typing `httpdmon` on the command line.

=== Screenshot(s) ===

See it in action...