@echo off
:: This Windows batch file sets open-editor.js as handler for editor:// protocol

if defined PROCESSOR_ARCHITEW6432 (set reg="%systemroot%\sysnative\reg.exe") else (set reg=reg)

%reg% ADD HKCR\editor /ve /d "URL:editor Protocol" /f
%reg% ADD HKCR\editor /v "URL Protocol" /d "" /f
%reg% ADD HKCR\editor\shell\open\command /ve /d "wscript \"%~dp0open-editor.js\" \"%%1\"" /f
