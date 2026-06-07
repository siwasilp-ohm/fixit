@echo off
chcp 65001 >nul
title FixIt — Force Pull (ลบ local changes)

echo.
echo  ╔════════════════════════════════════════════════╗
echo  ║   FORCE PULL — ลบ local changes ทั้งหมด!     ║
echo  ║   ข้อมูลที่แก้ไข local จะหายถาวร             ║
echo  ╚════════════════════════════════════════════════╝
echo.

where git >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] ไม่พบ git
    pause
    exit /b 1
)

for /f "tokens=*" %%b in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set BRANCH=%%b

echo  Branch : %BRANCH%
echo.
echo  คำเตือน: คำสั่งนี้จะลบ local changes ทั้งหมดที่ยังไม่ได้ commit
echo.
set /p CONFIRM= พิมพ์ YES เพื่อยืนยัน:
if /i not "%CONFIRM%"=="YES" (
    echo  ยกเลิกแล้ว
    pause
    exit /b 0
)

echo.
echo  [1/4] Fetch...
git fetch origin

echo  [2/4] Reset local changes...
git reset --hard origin/%BRANCH%

echo  [3/4] Clean untracked files...
git clean -fd

echo  [4/4] ผลลัพธ์:
git log --oneline -5

echo.
echo  Force Pull สำเร็จ!
pause
