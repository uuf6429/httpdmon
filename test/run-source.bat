@ECHO OFF

SETLOCAL ENABLEDELAYEDEXPANSION ENABLEEXTENSIONS

IF DEFINED ProgramFiles(x86) (SET BITNESS=64) else (SET BITNESS=32)
SET ANSICON="C:\Program Files\ansicon\ansicon%BITNESS%.exe"

SET TARGET="%CD%\..\init.php"
CD ..
%ANSICON% php %TARGET% -- -r -c

PAUSE