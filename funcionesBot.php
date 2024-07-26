<?php
require_once('config.php');

function obtenerApiKey() {
    return API_KEY;
}

function enviarPeticionTelegram($metodo, $parametros, $api_key, $timeout = 15, $reintentos = 3) {
    $url = "https://api.telegram.org/bot$api_key/$metodo";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($parametros),
        CURLOPT_TIMEOUT => $timeout
    ]);

    $resultado = curl_exec($ch);
    $intento = 0;

    while (curl_errno($ch) && $intento < $reintentos) {
        $resultado = curl_exec($ch);
        $intento++;
        usleep(300000);  // Espera de 300 ms entre reintentos
    }

    curl_close($ch);
    $respuesta = json_decode($resultado, true);
    if (!$respuesta || (isset($respuesta['ok']) && !$respuesta['ok'])) {
        error_log("Error en enviarPeticionTelegram: " . json_encode($respuesta)); // Registro de error
        return ['ok' => false, 'error' => $respuesta['description'] ?? 'Unknown error'];
    }
    return $respuesta;
}

function leerArchivoSeguro($ruta) {
    if (!file_exists($ruta)) {
        return false;
    }
    $archivo = fopen($ruta, 'r');
    if ($archivo) {
        if (flock($archivo, LOCK_SH)) {
            $contenido = fread($archivo, filesize($ruta));
            flock($archivo, LOCK_UN);
            fclose($archivo);
            return $contenido;
        }
        fclose($archivo);
    }
    return false;
}

function escribirArchivoSeguro($ruta, $contenido) {
    $archivo = fopen($ruta, 'c');
    if ($archivo) {
        if (flock($archivo, LOCK_EX)) {
            ftruncate($archivo, 0);
            fwrite($archivo, $contenido);
            fflush($archivo);
            flock($archivo, LOCK_UN);
            fclose($archivo);
            return true;
        }
        fclose($archivo);
    }
    return false;
}

function cargarUsuariosConocidos() {
    $archivoUsuarios = USERS_FILE;
    if (!file_exists($archivoUsuarios)) {
        escribirArchivoSeguro($archivoUsuarios, json_encode([]));
    }
    $contenido = leerArchivoSeguro($archivoUsuarios);
    return json_decode($contenido, true) ?? [];
}

function guardarUsuariosConocidos($usuariosConocidos) {
    return escribirArchivoSeguro(USERS_FILE, json_encode($usuariosConocidos));
}

function cargarMensajesEnviados() {
    $archivoMensajes = MENSAJES_FILE;
    if (!file_exists($archivoMensajes)) {
        escribirArchivoSeguro($archivoMensajes, json_encode([]));
    }
    $contenido = leerArchivoSeguro($archivoMensajes);
    return json_decode($contenido, true) ?? [];
}

function guardarMensajesEnviados($mensajesEnviados) {
    return escribirArchivoSeguro(MENSAJES_FILE, json_encode($mensajesEnviados));
}

function limpiarMensajesCaducados() {
    $mensajesEnviados = cargarMensajesEnviados();
    $nuevosMensajes = [];
    $tiempoActual = time();

    foreach ($mensajesEnviados as $mensaje) {
        if (($tiempoActual - $mensaje['timestamp']) < TIEMPO_EXPIRACION) {
            $nuevosMensajes[] = $mensaje;
        }
    }

    guardarMensajesEnviados($nuevosMensajes);
}

function validarEntrada($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = strip_tags($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}

function procesarSticker($id_chat, $sticker, $api_key) {
    try {
        if (empty($sticker['file_id'])) return;
        if (!file_exists("espera_pegatina_id_{$id_chat}.txt")) return;
        escribirArchivoSeguro("file_id_sticker.txt", $sticker['file_id']);
        unlink("espera_pegatina_id_{$id_chat}.txt");
        enviarFileIdPegatina($id_chat, $sticker['file_id'], $api_key);
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al procesar el sticker: " . $e->getMessage()
        ], $api_key);
    }
}

function procesarFoto($id_chat, $foto, $api_key) {
    try {
        if (empty($foto['file_id'])) return;
        if (!file_exists("espera_foto_id_{$id_chat}.txt")) return;
        escribirArchivoSeguro("file_id_foto.txt", $foto['file_id']);
        unlink("espera_foto_id_{$id_chat}.txt");
        enviarFileIdFoto($id_chat, $foto['file_id'], $api_key);
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al procesar la foto: " . $e->getMessage()
        ], $api_key);
    }
}

function procesarVideo($id_chat, $video, $api_key) {
    try {
        if (empty($video['file_id'])) return;
        if (!file_exists("espera_video_id_{$id_chat}.txt")) return;
        escribirArchivoSeguro("file_id_video.txt", $video['file_id']);
        unlink("espera_video_id_{$id_chat}.txt");
        enviarFileIdVideo($id_chat, $video['file_id'], $api_key);
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al procesar el video: " . $e->getMessage()
        ], $api_key);
    }
}

function enviarFileIdPegatina($id_chat, $stickerId, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "El file_id de la pegatina es:"
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $stickerId
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "💓¿Quieres enviar esta pegatina a todos los usuarios? Responde 'sí' o 'no'."
        ], $api_key);
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al enviar el file_id de la pegatina: " . $e->getMessage()
        ], $api_key);
    }
}

function enviarFileIdFoto($id_chat, $fotoId, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "El file_id de la foto es:"
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $fotoId
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🖼¿Quieres enviar esta foto a todos los usuarios? Responde 'sí' o 'no'."
        ], $api_key);
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al enviar el file_id de la foto: " . $e->getMessage()
        ], $api_key);
    }
}


function enviarFileIdVideo($id_chat, $videoId, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "El file_id del video es:"
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $videoId
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🎞🎥¿Quieres enviar este video a todos los usuarios? Responde 'sí' o 'no'."
        ], $api_key);
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al enviar el file_id del video: " . $e->getMessage()
        ], $api_key);
    }
}

function verificarEstadosEspera($id_chat, $user_id, $mensaje, $administradores, $api_key) {
    $archivo_mensaje = "espera_mensaje_{$id_chat}.txt";
    $archivo_comando = "espera_comando_{$id_chat}.txt";
    $archivo_pegatina = "file_id_sticker.txt";
    $archivo_foto = "file_id_foto.txt";
    $archivo_video = "file_id_video.txt";
    $archivo_eliminar = "espera_eliminar_{$id_chat}.txt";
    $archivo_limpiar = "espera_limpiar_{$id_chat}.txt";

    try {
        if (file_exists($archivo_mensaje)) {
            $mensajePrevio = leerArchivoSeguro($archivo_mensaje);

            if ($mensajePrevio === 'true') {
                escribirArchivoSeguro($archivo_mensaje, $mensaje);
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => "📃¿Estás seguro de que quieres enviar este mensaje a todos los usuarios? 🏤 Responde 'sí' para confirmar o 'no' para cancelar."
                ], $api_key);
            } else {
                manejarEnvioMensajeConfirmacion($id_chat, $mensaje, $mensajePrevio, $api_key);
                unlink($archivo_mensaje);
            }
        }
        if (file_exists($archivo_comando)) {
            $respuesta = procesarConsulta($mensaje, $user_id);
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => $respuesta
            ], $api_key);
            unlink($archivo_comando);
        }
        if (file_exists($archivo_pegatina)) {
            if (strtolower($mensaje) === 'sí' || strtolower($mensaje) === 'si' || strtolower($mensaje) === 'sí!' || strtolower($mensaje) === 'si!') {
                $stickerId = leerArchivoSeguro($archivo_pegatina);
                manejarEnvioPegatinaConfirmacion($id_chat, $stickerId, $api_key);
                unlink($archivo_pegatina);
            } elseif (strtolower($mensaje) === 'no') {
                unlink($archivo_pegatina);
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => "❌ Envío cancelado. ✂️"
                ], $api_key);
            }
        }
        if (file_exists($archivo_foto)) {
            if (strtolower($mensaje) === 'sí' || strtolower($mensaje) === 'si' || strtolower($mensaje) === 'sí!' || strtolower($mensaje) === 'si!') {
                $fotoId = leerArchivoSeguro($archivo_foto);
                manejarEnvioFotoConfirmacion($id_chat, $fotoId, $api_key);
                unlink($archivo_foto);
            } elseif (strtolower($mensaje) === 'no') {
                unlink($archivo_foto);
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => "❌ Envío cancelado. ✂️"
                ], $api_key);
            }
        }
        if (file_exists($archivo_video)) {
            if (strtolower($mensaje) === 'sí' || strtolower($mensaje) === 'si' || strtolower($mensaje) === 'sí!' || strtolower($mensaje) === 'si!') {
                $videoId = leerArchivoSeguro($archivo_video);
                manejarEnvioVideoConfirmacion($id_chat, $videoId, $api_key);
                unlink($archivo_video);
            } elseif (strtolower($mensaje) === 'no') {
                unlink($archivo_video);
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => "❌ Envío cancelado. ✂️"
                ], $api_key);
            }
        }
        if (file_exists($archivo_eliminar)) {
            if ($mensaje !== '') {
                eliminarMensajePasado($id_chat, $mensaje, $api_key);
                verificarTamanioArchivoMensajes($id_chat, $api_key);
                unlink($archivo_eliminar);
            } else {
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => "⚠️ No se ha proporcionado un Identificador válido. Por favor, inténtalo de nuevo."
                ], $api_key);
            }
        }
        if (file_exists($archivo_limpiar)) {
            procesarLimpiezaArchivo($id_chat, $api_key, $mensaje);
        }
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error: " . $e->getMessage()
        ], $api_key);
    }
}

function guardarConsultaNoRespondida($user_id, $consulta) {
    $archivoConsultas = 'consultas_no_respondidas.json';
    $consultas = [];

    if (file_exists($archivoConsultas)) {
        $contenido = file_get_contents($archivoConsultas);
        $consultas = json_decode($contenido, true) ?? [];
    }

    $consultas[] = [
        'user_id' => $user_id,
        'consulta' => $consulta,
        'timestamp' => time()
    ];

    file_put_contents($archivoConsultas, json_encode($consultas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function cargarConsultasNoRespondidas() {
    $archivoConsultas = 'consultas_no_respondidas.json';
    if (!file_exists($archivoConsultas)) {
        return [];
    }

    $contenido = file_get_contents($archivoConsultas);
    return json_decode($contenido, true) ?? [];
}

function eliminarConsultaNoRespondida($timestamp) {
    $archivoConsultas = 'consultas_no_respondidas.json';
    if (!file_exists($archivoConsultas)) {
        return;
    }

    $consultas = json_decode(file_get_contents($archivoConsultas), true) ?? [];
    $consultas = array_filter($consultas, function ($consulta) use ($timestamp) {
        return $consulta['timestamp'] !== $timestamp;
    });

    file_put_contents($archivoConsultas, json_encode(array_values($consultas), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function inicializarUsuario($id_chat, $user_id, $nombreUsuario, $apellidoUsuario, $api_key) {
    $usuariosConocidos = cargarUsuariosConocidos();
    $nombreCompleto = trim($nombreUsuario . " " . ($apellidoUsuario ?: ""));

    if (!isset($usuariosConocidos[$user_id])) {
        $usuariosConocidos[$user_id] = ['nombre' => $nombreUsuario, 'apellido' => $apellidoUsuario, 'chat_id' => $id_chat];
        if (!guardarUsuariosConocidos($usuariosConocidos)) {
            enviarPeticionTelegram("sendMessage", ['chat_id' => $id_chat, "text" => "⚠️ Error al registrar usuario."], $api_key);
            return;
        }
    }

    $mensajeBienvenida = "Hola, " . $nombreUsuario . "👋 Bienvenido a Astroturismo La Estación; estás hablando con el bot Astrobot. Gracias por activarme 🎉.\n" .
                         "$nombreUsuario; es un placer conocerte. Usa mi comando /ayuda para ver otros comandos disponibles para ti y así interactuar conmigo. 🙂\n\n" .
                         "📢 **Importante**: Para una mejor experiencia, asegúrate de tener la última versión de la aplicación de Telegram instalada.";
    
    enviarPeticionTelegram("sendMessage", ['chat_id' => $id_chat, "text" => $mensajeBienvenida], $api_key);
}

function cargarAdministradores() {
    $adminIds = file(ADMINS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('trim', $adminIds);
}

function esAdministrador($user_id) {
    $administradores = cargarAdministradores();
    return in_array($user_id, $administradores);
}

function mostrarAyuda($id_chat, $api_key, $administradores, $user_id, $nombreUsuario) {
    $esAdmin = in_array($user_id, $administradores) ? "👑" : "";
    $mensajeAyuda = "Hola $nombreUsuario $esAdmin! Aquí tienes los comandos disponibles:\n" .
                    "🔹 /inicializar - Inicializa Astrobot 💫\n" .
                    "🔹 /ayuda - Obtén los comandos y la ayuda que necesites\n" .
                    "🔹 /visualizar_telescopio - Si estás ahora en nuestra excursión pulsa aquí para poder ver lo mismo que nuestro ✨🔭\n" .
                    "🔹 /web - Navega por nuestra web sin salir de Telegram ⛵️🌍 (pulsa 'Iniciar' en la web app)\n" .
                    "🔹 /clima - Consulta el tiempo de AEMET (pulsa 'Iniciar' en la web app)\n" .
                    "🔹 /consultas - Pregúntame lo que quieras sobre nuestros productos o actividades; a ver si puedo resolver tus dudas.\n" .
                    "🔹 /contacto - Por si necesitas más información o ayuda 🌟\n";
    if ($esAdmin) {
        $mensajeAyuda .= "🔸 Comandos adicionales para administradores:\n" .
                         "⭐️ /mandar_mensaje - Enviar un mensaje a todos los usuarios\n" .
                         "⭐️ /mandar_pegatina - Enviar una pegatina a todos los usuarios\n" .
                         "⭐️ /mandar_foto - Enviar una foto a todos los usuarios\n" .
                         "⭐️ /mandar_video - Enviar un video a todos los usuarios\n" .
                         "⭐️ /eliminar_mensaje_pasado - Eliminar un mensaje enviado a todos los usuarios\n" .
                         "⭐️ /enlace_visualizar_telescopio - Activar/desactivar el enlace de visualización del telescopio\n" .
                         "⭐️ /configuracion_consultas - Configurar preguntas y respuestas\n";
    }
    enviarPeticionTelegram("sendMessage", [
        'chat_id' => $id_chat,
        "text" => $mensajeAyuda
    ], $api_key);
}

function iniciarConsulta($id_chat, $api_key) {
    escribirArchivoSeguro("espera_comando_{$id_chat}.txt", "true");
    enviarPeticionTelegram("sendMessage", [
        'chat_id' => $id_chat,
        "text" => "💬 ¿Qué quieres preguntarme? Escribe tu consulta ahora. 🤔"
    ], $api_key);
}

function procesarConsulta($input, $user_id) {
    $comandos = obtenerComandos();
    $sinonimos = obtenerSinonimos();
    $palabrasClave = extraerPalabrasClave($input);
    $comandosCoincidentes = buscarEnComandos($comandos, $palabrasClave, $sinonimos);
    if (!empty($comandosCoincidentes)) {
        return elegirMejorRespuesta($input, $comandosCoincidentes);
    } else {
        guardarConsultaNoRespondida($user_id, $input);
        return "❓ Lo siento, no pude encontrar una respuesta adecuada a tu consulta. Si lo consideras, puedes ponerte en /contacto con nosotros. 😕";
    }
}

function mostrarWeb($id_chat, $api_key) {
    $urlWeb = "Visita nuestra web 🔭 para más información: [Astroturismo La Estación](https://t.me/Astroturisbot/astroapp)";
    enviarPeticionTelegram("sendMessage", [
        'chat_id' => $id_chat,
        "text" => $urlWeb,
        "parse_mode" => "Markdown"
    ], $api_key);
}

function mostrarClima($id_chat, $api_key) {
    $infoClima = "☀️Consulta la previsión del clima en la web app:\n" .
                 "[Astroturismo La Estación Clima](https://t.me/Astroturisbot/Clima)\n\n" .
                 "Por si tuvieras algún problema 🌤, aquí te dejo el enlace directo visita la página oficial de la AEMET [aquí](https://www.aemet.es/es/eltiempo/prediccion).";
    enviarPeticionTelegram("sendMessage", [
        'chat_id' => $id_chat,
        "text" => $infoClima,
        "parse_mode" => "Markdown"
    ], $api_key);
}

function mostrarContacto($id_chat, $api_key) {
    $infoContacto = obtenerDatosContacto();
    enviarPeticionTelegram("sendMessage", [
        'chat_id' => $id_chat,
        "text" => $infoContacto,
        "parse_mode" => "Markdown",
        "disable_web_page_preview" => true
    ], $api_key);
}

function obtenerDatosContacto() {
    return "© Todos los derechos reservados.\n\n" .
           "**CONTACTA**\n" .
           "📬 Email: [info@astroturismolaestacion.es](mailto:info@astroturismolaestacion.es)\n" .
           "📞 Teléfono: +34 640 78 78 77\n\n" .
           "**SÍGUENOS EN**\n" .
           "[Instagram](https://www.instagram.com/astroturismolaestacion/)\n" .
           "[Facebook](https://www.facebook.com/Astroturismolaestacion)\n\n" .
           "🗺 **DIRECCIÓN**\n" .
           "Aldeanueva Del Camino (Cáceres)\n\n" .
           "Empresa de actividad turística alternativa con Nº de Registro: OA-CC-00177";
}

function iniciarEnvioMensaje($id_chat, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "📝 Por favor, escribe el mensaje que deseas enviar a todos los usuarios:"
        ], $api_key);
        escribirArchivoSeguro("espera_mensaje_{$id_chat}.txt", "true");
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al iniciar el envío de mensaje: " . $e->getMessage()
        ], $api_key);
    }
}

function manejarEnvioMensajeConfirmacion($id_chat, $respuesta, $mensaje, $api_key) {
    try {
        if (strtolower($respuesta) === "sí" || strtolower($respuesta) === "si") {
            $usuariosConocidos = cargarUsuariosConocidos();
            $totalEnviados = 0;
            $idUnico = "msg_" . uniqid();  // Generamos un ID único para este conjunto de mensajes

            $mensajesEnviados = cargarMensajesEnviados(); // Cargar mensajes existentes
            $fallos = 0;

            foreach ($usuariosConocidos as $usuario) {
                $response = enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $usuario['chat_id'],
                    "text" => $mensaje
                ], $api_key);

                if ($response && isset($response['result']['message_id'])) {
                    $totalEnviados++;
                    $messageId = $response['result']['message_id'];
                    $mensajesEnviados[] = [
                        'chat_id' => $usuario['chat_id'],
                        'message_id' => $messageId,
                        'file_id' => $idUnico,
                        'timestamp' => time(),
                        'tipo' => 'texto',
                        'contenido' => $mensaje
                    ];
                } else {
                    $fallos++;
                }

                usleep(300000);  // 300 ms de retraso entre cada mensaje
            }

            guardarMensajesEnviados($mensajesEnviados); // Guardar mensajes actualizados

            // Resumen del envío
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "Mensaje enviado a $totalEnviados usuarios. ID del envío:"
            ], $api_key);
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => $idUnico
            ], $api_key);

            // Notificar si hubo fallos
            if ($fallos > 0) {
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => "⚠️ La recepción del documento no ha sido registrada por todos los usuarios."
                ], $api_key);
            }

        } else {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "❌ Envío cancelado.✂️"
            ], $api_key);
        }
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al confirmar el envío del mensaje: " . $e->getMessage()
        ], $api_key);
    }
}

function manejarEnvioPegatinaConfirmacion($id_chat, $stickerId, $api_key) {
    try {
        $usuariosConocidos = cargarUsuariosConocidos();
        $totalEnviados = 0;
        $idUnico = "sticker_" . uniqid();  // Generamos un ID único para este conjunto de mensajes

        $mensajesEnviados = cargarMensajesEnviados(); // Cargar mensajes existentes
        $fallos = 0;

        foreach ($usuariosConocidos as $usuario) {
            $response = enviarPeticionTelegram("sendSticker", [
                'chat_id' => $usuario['chat_id'],
                "sticker" => $stickerId
            ], $api_key);

            if ($response && isset($response['result']['message_id'])) {
                $totalEnviados++;
                $messageId = $response['result']['message_id'];
                $mensajesEnviados[] = [
                    'chat_id' => $usuario['chat_id'],
                    'message_id' => $messageId,
                    'file_id' => $idUnico,
                    'timestamp' => time(),
                    'tipo' => 'sticker',
                    'contenido' => $stickerId
                ];
            } else {
                $fallos++;
            }

            usleep(300000);  // 300 ms de retraso entre cada mensaje
        }

        guardarMensajesEnviados($mensajesEnviados);

        // Resumen del envío
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Pegatina enviada a $totalEnviados usuarios. ID del envío:"
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $idUnico
        ], $api_key);

        // Notificar si hubo fallos
        if ($fallos > 0) {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "⚠️ La recepción del documento no ha sido registrada por todos los usuarios."
            ], $api_key);
        }
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al confirmar el envío de la pegatina: " . $e->getMessage()
        ], $api_key);
    }
}

function manejarEnvioFotoConfirmacion($id_chat, $fotoId, $api_key) {
    try {
        $usuariosConocidos = cargarUsuariosConocidos();
        $totalEnviados = 0;
        $idUnico = "photo_" . uniqid();  // Generamos un ID único para este conjunto de mensajes

        $mensajesEnviados = cargarMensajesEnviados(); // Cargar mensajes existentes
        $fallos = 0;

        foreach ($usuariosConocidos as $usuario) {
            $response = enviarPeticionTelegram("sendPhoto", [
                'chat_id' => $usuario['chat_id'],
                "photo" => $fotoId
            ], $api_key);

            if ($response && isset($response['result']['message_id'])) {
                $totalEnviados++;
                $messageId = $response['result']['message_id'];
                $mensajesEnviados[] = [
                    'chat_id' => $usuario['chat_id'],
                    'message_id' => $messageId,
                    'file_id' => $idUnico,
                    'timestamp' => time(),
                    'tipo' => 'foto',
                    'contenido' => $fotoId
                ];
            } else {
                $fallos++;
            }

            usleep(300000);  // 300 ms de retraso entre cada mensaje
        }

        guardarMensajesEnviados($mensajesEnviados); // Guardar mensajes actualizados

        // Resumen del envío
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Foto enviada a $totalEnviados usuarios. ID del envío:"
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $idUnico
        ], $api_key);

        // Notificar si hubo fallos
        if ($fallos > 0) {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "⚠️ La recepción del documento no ha sido registrada por todos los usuarios."
            ], $api_key);
        }
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al confirmar el envío de la foto: " . $e->getMessage()
        ], $api_key);
    }
}

function manejarEnvioVideoConfirmacion($id_chat, $videoId, $api_key) {
    try {
        $usuariosConocidos = cargarUsuariosConocidos();
        $totalEnviados = 0;
        $idUnico = "video_" . uniqid();  // Generamos un ID único para este conjunto de mensajes

        $mensajesEnviados = cargarMensajesEnviados(); // Cargar mensajes existentes
        $fallos = 0;

        foreach ($usuariosConocidos as $usuario) {
            $response = enviarPeticionTelegram("sendVideo", [
                'chat_id' => $usuario['chat_id'],
                "video" => $videoId
            ], $api_key);

            if ($response && isset($response['result']['message_id'])) {
                $totalEnviados++;
                $messageId = $response['result']['message_id'];
                $mensajesEnviados[] = [
                    'chat_id' => $usuario['chat_id'],
                    'message_id' => $messageId,
                    'file_id' => $idUnico,
                    'timestamp' => time(),
                    'tipo' => 'video',
                    'contenido' => $videoId
                ];
            } else {
                $fallos++;
            }

            usleep(300000);  // 300 ms de retraso entre cada mensaje
        }

        guardarMensajesEnviados($mensajesEnviados); // Guardar mensajes actualizados

        // Resumen del envío
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Video enviado a $totalEnviados usuarios. ID del envío:"
        ], $api_key);
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $idUnico
        ], $api_key);

        // Notificar si hubo fallos
        if ($fallos > 0) {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "⚠️ La recepción del documento no ha sido registrada por todos los usuarios."
            ], $api_key);
        }
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al confirmar el envío del video: " . $e->getMessage()
        ], $api_key);
    }
}

function iniciarCapturaIDPegatina($id_chat, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Por favor, envía la pegatina. Primero se obtendrá su Id_file y si lo envías te daré el ID de dicho envío."
        ], $api_key);
        escribirArchivoSeguro("espera_pegatina_id_{$id_chat}.txt", "true");
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al iniciar la captura del ID de la pegatina: " . $e->getMessage()
        ], $api_key);
    }
}

function iniciarCapturaIDFoto($id_chat, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Por favor, envía la foto. Primero se obtendrá su Id_file y si la envías te daré el ID de dicho envío."
        ], $api_key);
        escribirArchivoSeguro("espera_foto_id_{$id_chat}.txt", "true");
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al iniciar la captura del ID de la foto: " . $e->getMessage()
        ], $api_key);
    }
}

function iniciarCapturaIDVideo($id_chat, $api_key) {
    try {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Por favor, envía el video. Primero se obtendrá su Id_file y si lo envías te daré el ID de dicho envío."
        ], $api_key);
        escribirArchivoSeguro("espera_video_id_{$id_chat}.txt", "true");
    } catch (Exception $e) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ Ocurrió un error al iniciar la captura del ID del video: " . $e->getMessage()
        ], $api_key);
    }
}

function manejarEnlaceVisualizarTelescopio($id_chat, $api_key) {
    $estado_actual = leerArchivoSeguro(TELESCOPIO_FILE);
    
    if ($estado_actual === 'true') {
        escribirArchivoSeguro(TELESCOPIO_FILE, 'false');
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔭 El enlace para visualizar el telescopio ha sido desactivado.❌"
        ], $api_key);
    } else {
        escribirArchivoSeguro(TELESCOPIO_FILE, 'true');
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔭 El enlace para visualizar el telescopio ha sido activado.✅"
        ], $api_key);

        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔭 Puedes visualizar el telescopio en la web_app con el comando /visualizar_telescopio."
        ], $api_key);

        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔭 O en el siguiente enlace: [Visualizar Telescopio](https://www.granderrota.com/Astrobot/visualizar_telescopio.php)",
            "parse_mode" => "Markdown"
        ], $api_key);
    }
}

function mostrarVisualizarTelescopio($id_chat, $api_key) {
    $estado_actual = leerArchivoSeguro(TELESCOPIO_FILE);
    if ($estado_actual === 'true') {
        $urlWebApp = "https://t.me/Astroturisbot/telescopio_La_Estacion_app";
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔭 Puedes visualizar nuestro telescopio en el siguiente enlace: [Visualizar Telescopio]($urlWebApp)",
            "parse_mode" => "Markdown"
        ], $api_key);
    } else {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🔭 La visualización del telescopio está desactivada actualmente.❌",
        ], $api_key);
    }
}

function mostrarConfiguracionConsultas($id_chat, $api_key) {
    $urlConfig = "https://www.granderrota.com/Astrobot/cargador_comandos.php";
    enviarPeticionTelegram("sendMessage", [
        'chat_id' => $id_chat,
        "text" => "🔧 Puedes configurar las consultas en el siguiente enlace: [Configuración de Consultas]($urlConfig)",
        "parse_mode" => "Markdown"
    ], $api_key);
}

function eliminarMensajePasado($id_chat, $file_id, $api_key, $maxIntentos = 5) {
    $mensajesEnviados = cargarMensajesEnviados();
    $mensajeEncontrado = false;
    $totalEliminados = 0;
    $intentos = 0;

    // Recorrer todos los mensajes enviados para buscar coincidencias con el file_id proporcionado
    foreach ($mensajesEnviados as $index => $mensaje) {
        if ($mensaje['file_id'] === $file_id) {
            $response = enviarPeticionTelegram("deleteMessage", [
                'chat_id' => $mensaje['chat_id'],
                'message_id' => $mensaje['message_id']
            ], $api_key);

            if ($response && isset($response['ok']) && $response['ok']) {
                unset($mensajesEnviados[$index]);
                $mensajeEncontrado = true;
                $totalEliminados++;  // Incrementar el contador de eliminaciones exitosas
            } else {
                $intentos++;
                if ($intentos >= $maxIntentos) {
                    break;
                }
            }
        }
    }

    // Guardar la lista de mensajes actualizada
    guardarMensajesEnviados(array_values($mensajesEnviados));

    if ($mensajeEncontrado) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "🗑️ Mensaje con ID $file_id eliminado para $totalEliminados usuarios conocidos."
        ], $api_key);
    } else {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ No se encontró ningún mensaje con ID $file_id."
        ], $api_key);
    }
}

function iniciarEliminarMensajePasado($id_chat, $api_key) {
    $mensajesEnviados = cargarMensajesEnviados();
    $tiempoActual = time();
    $limiteTiempo = 48 * 3600; // 48 horas en segundos
    $mensajesRecientes = [];

    // Filtrar mensajes que tengan menos de 48 horas
    foreach ($mensajesEnviados as $mensaje) {
        if (($tiempoActual - $mensaje['timestamp']) <= $limiteTiempo) {
            $mensajesRecientes[] = $mensaje;
        }
    }

    if (empty($mensajesRecientes)) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "No se encontraron mensajes recientes 🤷🏻‍♂️"
        ], $api_key);
    } else {
        $listaMensajes = "📜 Últimos mensajes enviados en las últimas 48 horas:\n";
        
        // Crear un array para controlar duplicados
        $mensajesUnicos = [];

        foreach ($mensajesRecientes as $mensaje) {
            $contenido = isset($mensaje['contenido']) ? substr($mensaje['contenido'], 0, 20) : '';
            $mensajeTexto = "ID: " . $mensaje['file_id'] . " - Tipo: " . $mensaje['tipo'] . " - Contenido: " . $contenido;
            
            // Añadir solo mensajes únicos
            if (!in_array($mensajeTexto, $mensajesUnicos)) {
                $mensajesUnicos[] = $mensajeTexto;
                $listaMensajes .= $mensajeTexto . "\n";
            }
        }

        // Enviar lista de mensajes en un solo mensaje
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => $listaMensajes
        ], $api_key);

        // Enviar cada ID en un mensaje separado
        foreach ($mensajesUnicos as $mensajeTexto) {
            preg_match('/ID: (\S+)/', $mensajeTexto, $matches);
            if (isset($matches[1])) {
                enviarPeticionTelegram("sendMessage", [
                    'chat_id' => $id_chat,
                    "text" => $matches[1]
                ], $api_key);
            }
        }

        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "Por favor, proporciona el ID del mensaje que deseas eliminar."
        ], $api_key);

        escribirArchivoSeguro("espera_eliminar_{$id_chat}.txt", "true");
    }
}

function verificarTamanioArchivoMensajes($id_chat, $api_key) {
    $archivoMensajes = MENSAJES_FILE;
    $tamanioMaximo = 0.2 * 1024 * 1024; // 0.2 MB en bytes

    if (file_exists($archivoMensajes) && filesize($archivoMensajes) > $tamanioMaximo) {
        enviarPeticionTelegram("sendMessage", [
            'chat_id' => $id_chat,
            "text" => "⚠️ El archivo de mensajes ha superado los 0.2 MB. ¿Deseas realizar una limpieza? 🧽🪣 Responde 'sí' o 'no'."
        ], $api_key);
        escribirArchivoSeguro("espera_limpiar_{$id_chat}.txt", "true");
    }
}

function procesarLimpiezaArchivo($id_chat, $api_key, $respuesta) {
    $archivo_limpiar = "espera_limpiar_{$id_chat}.txt";

    if (file_exists($archivo_limpiar)) {
        if (strtolower($respuesta) === "sí" || strtolower($respuesta) === "si") {
            limpiarMensajesCaducados();
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "✅ Limpieza completada. ✨"
            ], $api_key);
        } else {
            enviarPeticionTelegram("sendMessage", [
                'chat_id' => $id_chat,
                "text" => "❌ Limpieza cancelada."
            ], $api_key);
        }
        unlink($archivo_limpiar);
    }
}
?>
