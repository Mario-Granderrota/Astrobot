<?php
// gestionar_consultas.php

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timestamp'])) {
    $timestamp = (int)$_POST['timestamp'];
    eliminarConsultaNoRespondida($timestamp);
    echo json_encode(['success' => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Consultas no Respondidas</title>
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
        .mensaje {
            background-color: #ffe4b5;
            padding: 15px;
            border-left: 5px solid #ffa07a;
            color: #333;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        .error {
            background-color: #fdecea;
            padding: 15px;
            border-left: 5px solid #e53935;
            color: #e53935;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        button {
            background-color: #d9534f;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            margin-top: 10px;
        }
        button:hover {
            background-color: #c9302c;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            background-color: #ffe4b5;
            padding: 15px;
            border-left: 5px solid #ffa07a;
            margin-bottom: 10px;
        }
        form {
            display: inline;
        }
        h1, h2 {
            color: #333;
        }
    </style>
    <script>
        function eliminarConsulta(timestamp) {
            if (confirm("¿Estás seguro de que quieres eliminar esta consulta?")) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'gestionar_consultas.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            document.getElementById('consulta-' + timestamp).remove();
                            if (document.querySelectorAll('li').length === 0) {
                                document.getElementById('consultas-container').innerHTML = "<p>No hay consultas sin respuesta.</p>";
                            }
                        } else {
                            alert('Error al eliminar la consulta');
                        }
                    }
                };
                xhr.send('timestamp=' + timestamp);
            }
        }
    </script>
</head>
<body>

<div class="container">
    <h1>Astroturismo La Estación</h1>
    <h2>Sistema de Gestión de Consultas no resueltas por Astrobot</h2>
    <p>Este es un sitio de análisis para un administrador de Astrobot. Aquí puedes consultar qué preguntas o consultas no han recibido una respuesta de Astrobot.</p>
    
    <h2>Consultas sin respuesta:</h2>
    <div id="consultas-container">
        <?php
        $consultas = cargarConsultasNoRespondidas();
        if (empty($consultas)) {
            echo "<p>No hay consultas sin respuesta.</p>";
        } else {
            echo "<ul>";
            foreach ($consultas as $consulta) {
                echo "<li id='consulta-" . htmlspecialchars($consulta['timestamp']) . "'>";
                echo "<strong>ID Usuario:</strong> " . htmlspecialchars($consulta['user_id']) . "<br>";
                echo "<strong>Consulta:</strong> " . htmlspecialchars($consulta['consulta']) . "<br>";
                echo "<strong>Timestamp:</strong> " . htmlspecialchars($consulta['timestamp']) . "<br>";
                echo "<button onclick='eliminarConsulta(" . htmlspecialchars($consulta['timestamp']) . ")'>Eliminar</button>";
                echo "</li>";
            }
            echo "</ul>";
        }
        ?>
    </div>
</div>

</body>
</html>
