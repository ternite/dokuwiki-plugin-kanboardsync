@echo off
setlocal EnableDelayedExpansion

if "%~1"=="" (
    echo Fehler: Server-URL fehlt.
    echo Aufruf:
    echo   %~nx0 https://192.168.0.100:10443
    exit /b 1
)

set "SERVER_URL=%~1"
set "SCRIPT_DIR=%~dp0"
set "LOGFILE=%SCRIPT_DIR%..\..\..\..\data\log\kanboard_sync.log"

echo logfile: %LOGFILE%
(
    echo ==================================================
    echo Start: %DATE% %TIME%

    curl -k "%SERVER_URL%/doku.php?id=start&do=kanboard_sync"

    echo.
    echo ExitCode: !ERRORLEVEL!
    echo Ende: %DATE% %TIME%
    echo.
) >> "%LOGFILE%" 2>&1