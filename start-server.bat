@echo off
title City Bus — Serveur PHP
echo.
echo  =========================================
echo   City Bus ^| Serveur PHP localhost:8000
echo  =========================================
echo.
echo  Ouvrir : http://localhost:8000
echo  Arreter : CTRL+C
echo.
cd /d "%~dp0"
c:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
pause
