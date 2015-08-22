@ECHO OFF

SETLOCAL ENABLEDELAYEDEXPANSION ENABLEEXTENSIONS

IF DEFINED ProgramFiles(x86) (SET BITNESS=64) else (SET BITNESS=32)
SET ANSICON="C:\Program Files\ansicon\ansicon%BITNESS%.exe"

SET TARGET="%CD%\..\build\httpdmon.php"
%ANSICON% php %TARGET% -- /r /c /i "../httpdmon.d/*.php"

PAUSE