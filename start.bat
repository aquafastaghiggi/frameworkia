@echo off
REM ============================================================
REM FRAMEWORKIA - XAMPP Startup Script for Windows
REM ============================================================

title Frameworkia - XAMPP Startup

echo.
echo ╔════════════════════════════════════════════════════════════════╗
echo ║        FRAMEWORKIA - XAMPP STARTUP SCRIPT                      ║
echo ╚════════════════════════════════════════════════════════════════╝
echo.

REM Check if XAMPP is installed
if not exist "C:\xampp\xampp-control.exe" (
    echo ❌ XAMPP não encontrado em C:\xampp
    echo.
    echo Instale XAMPP em: https://www.apachefriends.org/
    pause
    exit /b 1
)

echo ✅ XAMPP encontrado em C:\xampp
echo.

REM Get current directory
set FRAMEWORKIA_PATH=%CD%

REM Check if we're in frameworkia directory
if not exist "%FRAMEWORKIA_PATH%\public\index.html" (
    echo ❌ Não encontrei public/index.html
    echo.
    echo Execute este script da pasta raiz do Frameworkia:
    echo   C:\xampp\htdocs\frameworkia\start.bat
    pause
    exit /b 1
)

echo 🚀 Iniciando Frameworkia...
echo.

REM Check if .env exists
if not exist "%FRAMEWORKIA_PATH%\.env" (
    echo ⚠️  .env não encontrado. Criando a partir de .env.xampp...
    if exist "%FRAMEWORKIA_PATH%\.env.xampp" (
        copy "%FRAMEWORKIA_PATH%\.env.xampp" "%FRAMEWORKIA_PATH%\.env" >nul
        echo ✅ .env criado
    ) else if exist "%FRAMEWORKIA_PATH%\.env.example" (
        copy "%FRAMEWORKIA_PATH%\.env.example" "%FRAMEWORKIA_PATH%\.env" >nul
        echo ✅ .env criado
    )
)

echo.
echo 📁 Estrutura:
echo   • App Path: %FRAMEWORKIA_PATH%
echo   • XAMPP: C:\xampp
echo   • URL: http://localhost/frameworkia
echo.

REM Start XAMPP Control Panel
echo Iniciando XAMPP Control Panel...
echo.
start "" "C:\xampp\xampp-control.exe"

echo ⏳ Aguardando XAMPP iniciar...
timeout /t 3 /nobreak

echo.
echo ✅ PRONTO!
echo.
echo Próximos passos:
echo   1. No XAMPP Control Panel, clique "Start" em Apache
echo   2. No XAMPP Control Panel, clique "Start" em MySQL
echo   3. Abra seu navegador: http://localhost/frameworkia
echo   4. Login: admin / admin123
echo.
echo Se for primeira vez, execute setup:
echo   http://localhost/frameworkia/setup.php
echo.

pause
