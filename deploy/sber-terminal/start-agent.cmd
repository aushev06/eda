@echo off
rem Fidele: start the card terminal agent. Put the token from
rem Admin > Settings > payment terminal tab and the POS site address below.
chcp 65001 > nul
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fidele-terminal-agent.ps1" ^
    -Token "PASTE_TOKEN_HERE" ^
    -AllowedOrigin "https://fidele.185-237-186-210.sslip.io" ^
    -PilotDir "C:\sc552"
pause
