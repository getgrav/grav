@echo off

@setlocal

set GRAV_PATH=bin/

if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe

"%PHP_COMMAND%" "%GRAV_PATH%grav" %*

@endlocal
