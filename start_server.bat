@echo off
chcp 65001 >nul
title 开奖预测系统服务端

echo ========================================
echo   开奖预测系统 - 服务启动脚本
echo ========================================
echo.

REM 检查PHP是否安装
php --version >nul 2>&1
if errorlevel 1 (
    echo [错误] 未检测到PHP，请先安装PHP并添加到PATH
    pause
    exit /b 1
)

echo [OK] PHP已检测到
echo.

REM 获取脚本所在目录
set SCRIPT_DIR=%~dp0

REM 启动守护进程模式
echo [启动] 正在启动服务端守护进程...
echo [提示] 按 Ctrl+C 可以停止服务
echo.

cd /d "%SCRIPT_DIR%"
start "开奖预测服务端" cmd /c "php -r \"while(true){echo date('Y-m-d H:i:s').' 执行中...\n';system('php server.php');sleep(60);}\""

echo [完成] 服务已在后台启动
echo.
echo 启动PHP内置服务器（可选）:
echo   php -S localhost:8080
echo.
pause
