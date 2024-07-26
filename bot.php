<?php
require 'config.php';

$api_key = obtenerApiKey();
$administradores = cargarAdministradores();
$usuariosConocidos = cargarUsuariosConocidos();

$contenido = file_get_contents("php://input");
$actualizacion = json_decode($contenido, true);

if (!isset($actualizacion["message"])) {
    exit('No message');
}

$id_chat = $actualizacion["message"]["chat"]["id"];
$user_id = $actualizacion["message"]["from"]["id"];
$nombreUsuario = $actualizacion["message"]["from"]["first_name"] ?? "Usuario";
$apellidoUsuario = $actualizacion["message"]["from"]["last_name"] ?? "";
$mensaje = $actualizacion["message"]["text"] ?? "";

// Procesamiento de stickers
if (isset($actualizacion["message"]["sticker"])) {
    procesarSticker($id_chat, $actualizacion["message"]["sticker"], $api_key);
    exit;
}

// Procesamiento de fotos
if (isset($actualizacion["message"]["photo"])) {
    procesarFoto($id_chat, end($actualizacion["message"]["photo"]), $api_key);
    exit;
}

// Procesamiento de videos
if (isset($actualizacion["message"]["video"])) {
    procesarVideo($id_chat, $actualizacion["message"]["video"], $api_key);
    exit;
}

$mensaje = validarEntrada($mensaje);

// Verificar estados de espera
verificarEstadosEspera($id_chat, $user_id, $mensaje, $administradores, $api_key);

// Comprobar si el mensaje es un comando
if (substr($mensaje, 0, 1) === '/') {
    procesarComando($id_chat, $user_id, $mensaje, $administradores, $api_key, $nombreUsuario, $apellidoUsuario);
} else {
    manejarMensajeNoComando($id_chat, $user_id, $mensaje, $api_key);
}

function procesarComando($id_chat, $user_id, $mensaje, $administradores, $api_key, $nombreUsuario, $apellidoUsuario) {
    $comando = strtok($mensaje, " ");
    if (!in_array($comando, ['/start', '/inicializar', '/ayuda', '/consultas', '/web', '/clima', '/contacto', '/visualizar_telescopio', '/mandar_mensaje', '/mandar_pegatina', '/mandar_foto', '/mandar_video', '/eliminar_mensaje_pasado', '/enlace_visualizar_telescopio', '/configuracion_consultas'])) {
        if (!in_array($user_id, $administradores)) {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "🚫 No tienes permiso para usar este comando."
            ], $api_key);
            return;
        }
    }

    switch ($comando) {
        case "/start":
        case "/inicializar":
            inicializarUsuario($id_chat, $user_id, $nombreUsuario, $apellidoUsuario, $api_key);
            if (in_array($user_id, $administradores)) {
                verificarYRestablecerWebhook($id_chat, $api_key);
            }
            break;
        case "/ayuda":
            mostrarAyuda($id_chat, $api_key, $administradores, $user_id, $nombreUsuario);
            break;
        case "/web":
            mostrarWeb($id_chat, $api_key);
            break;
        case "/clima":
            mostrarClima($id_chat, $api_key);
            break;
        case "/contacto":
            mostrarContacto($id_chat, $api_key);
            break;
        case "/consultas":
            iniciarConsulta($id_chat, $api_key);
            break;
        case "/visualizar_telescopio":
            mostrarVisualizarTelescopio($id_chat, $api_key);
            break;
        case "/mandar_mensaje":
            iniciarEnvioMensaje($id_chat, $api_key);
            break;
        case "/mandar_pegatina":
            iniciarCapturaIDPegatina($id_chat, $api_key);
            break;
        case "/mandar_foto":
            iniciarCapturaIDFoto($id_chat, $api_key);
            break;
        case "/mandar_video":
            iniciarCapturaIDVideo($id_chat, $api_key);
            break;
        case "/eliminar_mensaje_pasado":
            iniciarEliminarMensajePasado($id_chat, $api_key);
            break;
        case "/enlace_visualizar_telescopio":
            manejarEnlaceVisualizarTelescopio($id_chat, $api_key);
            break;
        case "/configuracion_consultas":
            mostrarConfiguracionConsultas($id_chat, $api_key);
            break;
        default:
            enviarMensajeDesconocido($id_chat, $api_key);
            break;
    }
}

function manejarMensajeNoComando($id_chat, $user_id, $mensaje, $api_key) {
    // Lógica adicional para mensajes que no son comandos, si es necesario
}

function verificarYRestablecerWebhook($id_chat, $api_key) {
    $urlWebhook = "https://granderrota.com/Astrobot/bot.php"; // Reemplaza con tu URL de webhook

    // Verificar el webhook actual
    $respuesta = enviarPeticionTelegram("getWebhookInfo", [], $api_key);

    if (!$respuesta['ok'] || $respuesta['result']['url'] !== $urlWebhook) {
        // Establecer el webhook
        $respuesta = enviarPeticionTelegram("setWebhook", [
            'url' => $urlWebhook
        ], $api_key);

        if ($respuesta['ok']) {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "✅ Webhook de Astrobot configurado correctamente."
            ], $api_key);
        } else {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "⚠️ Error al configurar el webhook de Astrobot con Telegram: " . $respuesta['description']
            ], $api_key);
        }
    } else {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔗 Webhook de Astrobot ya está configurado correctamente."
        ], $api_key);
    }
}
?>
