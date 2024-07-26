<?php
define('API_KEY', trim(file_get_contents('token.txt'))); 
define('USERS_FILE', 'usuariosConocidos.json');
define('ADMINS_FILE', 'administradores.txt');
define('MENSAJES_FILE', 'mensajes.json');
define('TIEMPO_EXPIRACION', 86400); // 24 horas
define('TELESCOPIO_FILE', 'access_enabled.txt');
define('CONSULTAS_FILE', 'consultas_no_respondidas.json');
define('LOG_ACTIVADO', false);

if (!file_exists('token.txt')) {
    die('Error: El archivo con el token de Astrobot no existe.');
}
if (!file_exists(ADMINS_FILE)) {
    die('Error: El archivo de administradores de Astrobot no existe.');
}

require_once('funcionesBot.php');
require_once('funcionesLenguaje.php'); 

/*
> Archivos de texto que se manejan en este sistema de Astrobot
token.txt: Contiene el token de la API de Telegram.
administradores.txt: Lista de IDs de administradores.
access_enabled.txt: Estado del acceso al telescopio.
espera_mensaje_{$id_chat}.txt: Estado de espera para el envío de un mensaje.
espera_comando_{$id_chat}.txt: Estado de espera para el procesamiento de un comando.
espera_eliminar_{$id_chat}.txt: Estado de espera para eliminar un mensaje.

> Archivos JSON que se manejan en este sistema de Astrobot
usuariosConocidos.json: Lista de usuarios conocidos.
mensajes.json: Lista de mensajes enviados.
consultas_no_respondidas.json: Lista de consultas no respondidas.
*/
?>
