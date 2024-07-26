<?php
// cargador_comandos.php

ob_start();
include 'jsoneador_de_texto_plano.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivocomandos'])) {
    $archivo = $_FILES['archivocomandos'];

    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $tipoArchivo = mime_content_type($archivo['tmp_name']);
        if ($tipoArchivo == 'text/plain') {
            $rutaDestino = 'comandos.txt';

            if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                convertirTextoAJson($rutaDestino, 'comandos.json', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
                echo "Archivo cargado y procesado con éxito.";
            } else {
                echo "Error al mover el archivo.";
            }
        } else {
            echo "Tipo de archivo no permitido. Solo se permiten archivos de texto plano.";
        }
    } else {
        echo "Error al subir el archivo: " . $archivo['error'];
    }
} else {
    echo "No se ha subido ningún archivo.";
}

header('Location: indexAstrobot.php');
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrador de Comandos</title>
</head>
<body>
    <h2>Cargar archivo de comandos:</h2>
    <form method="post" enctype="multipart/form-data" action="cargador_comandos.php">
        <input type="file" name="archivocomandos" required>
        <input type="submit" value="Cargar">
    </form>

    <h2>Gestión de Consultas no Respondidas:</h2>
    <form method="get" action="gestionar_consultas.php">
        <input type="submit" value="Consultas sin respuesta">
    </form>
</body>
</html>
