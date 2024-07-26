<?php
// indexAstrobot.php

ini_set('display_errors', 1); // Poner a 1 para mostrar errores
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();

require_once 'config.php';
require_once 'funcionesLenguaje.php';
require_once 'validar_token.php'; // Asegúrate de que este archivo existe y contiene la función validarToken

$respuesta = '';
$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivocomandos'])) {
    $primerCaracter = trim($_POST['primerCaracter']);
    $ultimoCaracter = trim($_POST['ultimoCaracter']);
    $tokenIngresado = $primerCaracter . '...' . $ultimoCaracter;

    try {
        if (!validarToken($tokenIngresado)) {
            $_SESSION['error'] = "Token inválido. No se puede actualizar el archivo JSON.";
            header('Location: indexAstrobot.php');
            exit();
        }

        $archivo = $_FILES['archivocomandos'];

        if ($archivo['error'] === UPLOAD_ERR_OK) {
            $tipoArchivo = mime_content_type($archivo['tmp_name']);
            if ($tipoArchivo == 'text/plain') {
                $rutaDestino = 'comandos.txt';

                if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                    require_once 'jsoneador_de_texto_plano.php';
                    convertirTextoAJson($rutaDestino, 'comandos.json', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
                    $_SESSION['mensaje'] = "Archivo cargado y procesado con éxito.";
                } else {
                    $_SESSION['mensaje'] = "Error al mover el archivo.";
                }
            } else {
                $_SESSION['mensaje'] = "Tipo de archivo no permitido. Solo se permiten archivos de texto plano.";
            }
        } else {
            $_SESSION['mensaje'] = "Error al subir el archivo.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header('Location: indexAstrobot.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pregunta'])) {
    $pregunta = trim($_POST['pregunta']);

    if (mb_strlen($pregunta) < 3) {
        $_SESSION['mensaje'] = "La pregunta debe tener al menos 3 caracteres.";
    } else {
        try {
            $comandos = obtenerComandos();
            $sinonimos = obtenerSinonimos();
            $palabrasClave = extraerPalabrasClave($pregunta);
            $comandosCoincidentes = buscarEnComandos($comandos, $palabrasClave, $sinonimos);
            $respuesta = elegirMejorRespuesta($pregunta, $comandosCoincidentes);
            $_SESSION['respuesta'] = $respuesta;
        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al procesar la pregunta: " . $e->getMessage();
        }
    }

    header('Location: indexAstrobot.php');
    exit();
}

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['respuesta'])) {
    $respuesta = $_SESSION['respuesta'];
    unset($_SESSION['respuesta']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Astroturismo La Estación - Configuración de Consultas de Astrobot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .pregunta, .respuesta, .actualizacion, .subida-archivo, .gestionar-consultas {
            margin-bottom: 20px;
        }
        .respuesta p, .actualizacion p, .mensaje, .error {
            padding: 15px;
            border-left: 5px solid;
            color: #333;
            font-size: 1.1em;
        }
        .respuesta p, .actualizacion p, .mensaje {
            background-color: #d4edda;
            border-color: #155724;
            color: #155724;
        }
        .error {
            background-color: #fdecea;
            border-color: #e53935;
        }
        button {
            background-color: #34c759;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            margin-top: 10px;
        }
        button:hover {
            background-color: #2ca345;
        }
        .oculto {
            display: none;
        }
        .instrucciones {
            font-size: 1em;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e4f9f5;
            border-left: 5px solid #34c759;
        }
        textarea {
            width: 100%;
            height: 200px;
            margin-bottom: 20px;
            font-size: 1em;
            padding: 10px;
        }
        input[type="text"], input[type="file"] {
            width: calc(100% - 22px);
            padding: 10px;
            font-size: 1em;
            margin-bottom: 10px;
        }
        .comando-respuesta {
            background-color: #f0f0f0;
            padding: 10px;
            border-left: 3px solid #ccc;
            margin-bottom: 10px;
        }
        .gestionar-consultas {
            background-color: #ffe4b5;
            padding: 15px;
            border-left: 5px solid #ffa07a;
        }
        .gestionar-consultas button {
            background-color: #d9534f;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            margin-top: 10px;
        }
        .gestionar-consultas button:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Astroturismo La Estación</h1>
    <h2>Sistema de Configuración de Consultas de Astrobot</h2>
    <p>Este es un sitio de pruebas para un administrador de Astrobot. Aquí puedes hacer preguntas y recibir respuestas basadas en los comandos configurados.</p>
    
    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="instrucciones">
        <p>Para actualizar los archivos de preguntas y respuestas, puedes subir un archivo de texto plano con el formato adecuado. Si no quieres perder el contenido actual, copia el contenido del archivo existente antes de subir uno nuevo.</p>
        <p>El archivo de texto plano debe tener el siguiente formato:</p>
        <div class="comando-respuesta">
            <p><strong>Comando:</strong> Realizar reserva para observación de estrellas</p>
            <p><strong>Respuesta:</strong> Puedes realizar tu reserva online para una noche estrellada en https://astroturismolaestacion.es/ 🌌✨</p>
        </div>
        <div class="comando-respuesta">
            <p><strong>Comando:</strong> ¿Qué puedo ver en una noche de observación?</p>
            <p><strong>Respuesta:</strong> Durante nuestras observaciones podrás ver constelaciones, planetas, y, si tienes suerte, alguna lluvia de meteoros. 🌠🔭</p>
        </div>
        <div class="comando-respuesta">
            <p><strong>Comando:</strong> ¿Cómo puedo evitar la contaminación lumínica en mis fotos?</p>
            <p><strong>Respuesta:</strong> Asegúrate de utilizar un filtro de luz baja y elige lugares lejos de las ciudades como los recomendados en Astroturismo La Estación. 📸🌃</p>
        </div>
        <div class="comando-respuesta">
            <p><strong>Comando:</strong> ¿Qué equipamiento necesito para el astroturismo?</p>
            <p><strong>Respuesta:</strong> Con nosotros no necesitas nada especial; sólo ropa adecuada. 🧭🔭</p>
        </div>
        <p>Nota: El archivo de texto debe ser en formato UTF-8 para asegurar que los emoticonos se procesen correctamente.</p>
    </div>

    <div class="pregunta" id="pregunta">
        <form action="indexAstrobot.php" method="post">
            <label for="preguntaInput">Escribe tu pregunta:</label>
            <input type="text" id="preguntaInput" name="pregunta" required>
            <button type="submit">Enviar pregunta</button>
        </form>
    </div>

    <div class="respuesta" id="respuesta">
        <?php if ($respuesta): ?>
            <p>Respuesta: <?= htmlspecialchars($respuesta) ?></p>
        <?php endif; ?>
    </div>

    <div class="actualizacion">
        <button id="botonActualizar">Ver Contenido Actual</button>
    </div>

    <div class="actualizacion oculto" id="seccionActualizacion">
        <textarea id="contenidoComandos" readonly>
            <?php
            // Leer y mostrar el contenido de comandos.txt
            echo htmlspecialchars(file_get_contents('comandos.txt'));
            ?>
        </textarea>
    </div>

    <div class="subida-archivo">
        <h2>Subir Archivo de Comandos</h2>
        <form action="indexAstrobot.php" method="post" enctype="multipart/form-data">
            <p>Para utilizar esta sección de cambio de archivo, es necesario introducir la primera y última parte de la clave del token. (El administrador sabrá de qué se trata).</p>
            <label for="primerCaracter">Primer carácter del token:</label>
            <input type="text" id="primerCaracter" name="primerCaracter" required>
            <label for="ultimoCaracter">Último carácter del token:</label>
            <input type="text" id="ultimoCaracter" name="ultimoCaracter" required>
            <input type="file" name="archivocomandos" accept=".txt" required>
            <button type="submit">Subir Archivo</button>
        </form>
    </div>

    <div class="gestionar-consultas">
        <h2>Gestión de Consultas no Respondidas:</h2>
        <form method="get" action="gestionar_consultas.php">
            <button type="submit">Consultas sin respuesta</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('botonActualizar').addEventListener('click', function() {
        document.getElementById('seccionActualizacion').classList.toggle('oculto');
    });
</script>

</body>
</html>
