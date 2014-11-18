@echo off

@setlocal

set GPM_PATH=bin/

if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe

"%PHP_COMMAND%" "%GPM_PATH%gpm" %*

@endlocal
