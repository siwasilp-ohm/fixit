@echo off
chcp 65001 >nul
title FixIt — Git Push

echo.
echo  ╔══════════════════════════════════════╗
echo  ║   FixIt — Commit ^& Push to remote   ║
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

:: ── Check git repo ─────────────────────────────────────────
git rev-parse --git-dir >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] โฟลเดอร์นี้ไม่ใช่ Git repository
    pause
    exit /b 1
)

:: ── Show current branch ────────────────────────────────────
for /f "tokens=*" %%b in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set BRANCH=%%b
echo  Branch ปัจจุบัน : %BRANCH%
echo.

:: ── Show status ────────────────────────────────────────────
echo  ── ไฟล์ที่มีการเปลี่ยนแปลง ────────────────────────
git status --short
echo.

:: ── Check if anything changed ─────────────────────────────
git diff --quiet HEAD 2>nul
set DIRTY=%errorlevel%
git ls-files --others --exclude-standard | findstr . >nul 2>&1
set UNTRACKED=%errorlevel%

if %DIRTY%==0 if %UNTRACKED%==1 (
    echo  ไม่มีการเปลี่ยนแปลง — ไม่มีอะไรต้อง push
    echo.
    :: ยังสามารถ push ได้ถ้ามี commit ที่ยังไม่ได้ push
    goto :CHECK_UNPUSHED
)

:: ── Ask for commit message ─────────────────────────────────
echo  ── กรอก Commit Message ─────────────────────────────
echo  (กด Enter เพื่อใช้ข้อความอัตโนมัติตามวันเวลา)
echo.
set /p MSG= Commit message:

:: ── Default message if empty ──────────────────────────────
if "%MSG%"=="" (
    for /f "tokens=1-2 delims= " %%a in ('wmic os get localdatetime /value ^| find "="') do set DT=%%b
    set MSG=Update %DATE% %TIME:~0,5%
)

:: ── Stage all changes ──────────────────────────────────────
echo.
echo  [1/3] Staging all changes...
git add -A
if %errorlevel% neq 0 (
    echo  [ERROR] git add ไม่สำเร็จ
    pause
    exit /b 1
)

:: ── Commit ─────────────────────────────────────────────────
echo  [2/3] Committing: "%MSG%"
git commit -m "%MSG%"
if %errorlevel% neq 0 (
    echo  [ERROR] git commit ไม่สำเร็จ หรือไม่มีการเปลี่ยนแปลง
    pause
    exit /b 1
)

:: ── Push ───────────────────────────────────────────────────
:PUSH
echo  [3/3] Pushing to origin/%BRANCH%...
git push -u origin %BRANCH%
if %errorlevel% neq 0 (
    echo.
    echo  [WARN] push ครั้งแรกไม่สำเร็จ — กำลังลองใหม่...
    timeout /t 2 >nul
    git push -u origin %BRANCH%
    if %errorlevel% neq 0 (
        echo.
        echo  [ERROR] push ไม่สำเร็จ
        echo  อาจเกิดจาก:
        echo    - Remote มีการเปลี่ยนแปลงที่ยังไม่ได้ pull
        echo      วิธีแก้: รัน pull.bat ก่อน แล้วรัน push.bat ใหม่
        echo    - ไม่มีสิทธิ์ push ไปยัง branch นี้
        echo    - ไม่มีการเชื่อมต่ออินเทอร์เน็ต
        pause
        exit /b 1
    )
)

:: ── Check unpushed commits only ───────────────────────────
:CHECK_UNPUSHED
git log origin/%BRANCH%..HEAD --oneline 2>nul | findstr . >nul
if %errorlevel%==0 (
    if %DIRTY%==0 if %UNTRACKED%==1 (
        echo  พบ commit ที่ยังไม่ได้ push:
        git log origin/%BRANCH%..HEAD --oneline
        echo.
        set /p DOPUSH= ต้องการ push ไหม? (Y/N):
        if /i "%DOPUSH%"=="Y" goto :PUSH
        echo  ยกเลิกแล้ว
        pause
        exit /b 0
    )
)

:: ── Summary ────────────────────────────────────────────────
echo.
echo  ── Commit ล่าสุด 3 รายการ ───────────────────────────
git log --oneline -3
echo.
echo  ╔══════════════════════════════════════╗
echo  ║     Push สำเร็จเรียบร้อย!  ✓        ║
echo  ╚══════════════════════════════════════╝
echo.
pause
