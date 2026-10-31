@echo off
title Conector WhatsApp GDS
cd /d "%~dp0"
where node >nul 2>nul || (echo Instale o Node.js em https://nodejs.org - versao LTS - e abra de novo. & pause & exit /b)
if not exist node_modules (echo Instalando... so na primeira vez & call npm install --omit=dev)
:ligar
node server.js
echo O conector parou. Religando em 5 segundos... (feche esta janela para desligar)
timeout /t 5 >nul
goto ligar
