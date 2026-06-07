@echo off
chcp 65001 >nul
title FixIt — Git Pull

echo.
echo  ╔══════════════════════════════════════╗
echo  ║   FixIt — Pull latest from remote   ║
echo  ╚══════════════════════════════════════╝
echo.

:: ── Check git ──────────────────────────────────────────────
where git >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] ไม่พบ git กรุณาติดตั้ง Git for Windows ก่อน
    echo          https://git-scm.com/download/win
    pause
    exit /b 1
)

:: ── Show current branch ────────────────────────────────────
for /f "tokens=*" %%b in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set BRANCH=%%b
if "%BRANCH%"=="" (
    echo  [ERROR] ไม่ใช่ Git repository
    pause
    exit /b 1
)
echo  Branch ปัจจุบัน : %BRANCH%

:: ── Show last commit before pull ───────────────────────────
for /f "tokens=*" %%h in ('git log -1 --format^="%%h %%s" 2^>nul') do set LAST=%%h
echo  Commit ล่าสุด  : %LAST%
echo.

:: ── Fetch + Pull ───────────────────────────────────────────
echo  [1/3] Fetching from origin...
git fetch origin
if %errorlevel% neq 0 (
    echo  [ERROR] fetch ไม่สำเร็จ — ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต
    pause
    exit /b 1
)

echo  [2/3] Pulling branch: %BRANCH%
git pull origin %BRANCH%
if %errorlevel% neq 0 (
    echo.
    echo  [WARN] pull ไม่สำเร็จ อาจมี conflict หรือ local changes
    echo  ตัวเลือก:
    echo    1. แก้ conflict แล้วรัน git add / git commit
    echo    2. ยกเลิก local changes: git checkout -- .
    echo    3. Force pull (ลบ local changes ทั้งหมด): รัน force-pull.bat
    pause
    exit /b 1
)

echo  [3/3] แสดง commit ล่าสุดหลัง pull
echo.
git log --oneline -5
echo.

:: ── Done ───────────────────────────────────────────────────
echo  ╔══════════════════════════════════════╗
echo  ║        Pull สำเร็จเรียบร้อย!        ║
echo  ╚══════════════════════════════════════╝
echo.
pause
