@echo off
TITLE NostalgiaCore server software for Minecraft: Pocket Edition
cd /d %~dp0
if exist bin\php\php.exe (
	if exist bin\mintty.exe (
		start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o CursorType=0 -o CursorBlinks=1 -h error -t "NostalgiaCore" -i bin/pocketmine.ico -w max bin\php8test\php.exe -d enable_dl=On PocketMine-MP.php --enable-ansi %*
	) else (
		bin\php\php.exe -d enable_dl=On PocketMine-MP.php %*
	)
) else (
	if exist bin\mintty.exe (
		start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o CursorType=0 -o CursorBlinks=1 -h error -t "NostalgiaCore" -i bin/pocketmine.ico -w max php -d enable_dl=On PocketMine-MP.php --enable-ansi %*
	) else (
		php -d enable_dl=On PocketMine-MP.php %*
	)
)

