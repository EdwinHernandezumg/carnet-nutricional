#!/bin/bash
# Deja la base video_local como copia limpia de demo_local, con la enfermera del video.
M=/c/xampp/mysql/bin/mysql; D=/c/xampp/mysql/bin/mysqldump
$M -u root -e "DROP DATABASE IF EXISTS video_local; CREATE DATABASE video_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$D -u root demo_local | $M -u root video_local
H=$(/c/xampp/php/php.exe -r "echo password_hash('VideoPrueba2026', PASSWORD_DEFAULT);")
$M -u root -D video_local -e "UPDATE usuario SET usuario='ltzul', contrasena_hash='$H', debe_cambiar_pass=0, correo='lucia.tzul@example.com' WHERE usuario='demo.ltzul'; UPDATE usuario SET correo='rosa.tzul@example.com' WHERE usuario='rtzul860';"
echo "base video_local lista"
