@echo off
title MOTOR WHATSAPP - ControlEscolar
color 0b
echo ==========================================
echo    INICIANDO MOTOR DE WHATSAPP
echo ==========================================
echo.
cd /d %~dp0whatsapp-bot
node bot.js
pause
