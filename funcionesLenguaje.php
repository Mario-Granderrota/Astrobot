<?php
// funcionesLenguaje.php

define('RUTA_ARCHIVO_COMANDOS', 'comandos.json');
define('RUTA_ARCHIVO_SINONIMOS', 'sinonimos.json');

if (!file_exists(RUTA_ARCHIVO_COMANDOS)) {
    throw new Exception("El archivo JSON de comandos no existe: " . RUTA_ARCHIVO_COMANDOS);
}

if (!file_exists(RUTA_ARCHIVO_SINONIMOS)) {
    throw new Exception("El archivo de sinónimos no existe: " . RUTA_ARCHIVO_SINONIMOS);
}

function normalizarTexto($texto) {
    $texto = mb_strtolower($texto, 'UTF-8'); // Convertir a minúsculas
    $texto = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'n'],
        $texto
    );
    $texto = preg_replace('/[^\p{L}\p{N}\s]/u', '', $texto); // Eliminar caracteres especiales
    return $texto;
}

function obtenerComandos() {
    $json = file_get_contents(RUTA_ARCHIVO_COMANDOS);
    $comandos = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar JSON de comandos: " . json_last_error_msg());
    }
    return $comandos;
}

function obtenerSinonimos() {
    $json = file_get_contents(RUTA_ARCHIVO_SINONIMOS);
    $sinonimos = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar JSON de sinónimos: " . json_last_error_msg());
    }
    return $sinonimos;
}

function extraerPalabrasClave($texto) {
    $stopwords = file('stopwords_es.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $texto = normalizarTexto($texto);
    $texto = preg_replace('/\P{L}+/u', ' ', $texto); // Eliminar signos de puntuación
    $palabras = preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY);

    $palabrasClave = array_filter($palabras, function ($palabra) use ($stopwords) {
        return !in_array($palabra, $stopwords) && strlen($palabra) > 2;
    });

    return array_values($palabrasClave);
}

function buscarSinonimos($palabra, $sinonimos) {
    $palabraNormalizada = normalizarTexto($palabra);
    $sinonimosEncontrados = [];

    foreach ($sinonimos as $grupoSinonimos) {
        if (in_array($palabraNormalizada, array_map('normalizarTexto', $grupoSinonimos))) {
            $sinonimosEncontrados = array_merge($sinonimosEncontrados, $grupoSinonimos);
        }
    }

    // Incluir la palabra clave si no está en los sinónimos encontrados
    if (!in_array($palabra, $sinonimosEncontrados)) {
        array_unshift($sinonimosEncontrados, $palabra);
    }

    return array_unique(array_map('normalizarTexto', $sinonimosEncontrados));
}

function calcularPuntuacionPorProximidad($comando, $palabrasClave) {
    $puntuacionPorProximidad = 0;

    // Convertir el comando a un arreglo de palabras
    $palabrasComando = explode(' ', $comando);

    // Revisar cada palabra clave
    foreach ($palabrasClave as $palabraClave) {
        $posicionPalabraClave = array_search($palabraClave, $palabrasComando);

        // Si la palabra clave está en el comando
        if ($posicionPalabraClave !== false) {
            // Verificar las palabras adyacentes a la palabra clave
            foreach ([-1, 1] as $desplazamiento) {
                $posicionVecina = $posicionPalabraClave + $desplazamiento;

                if (isset($palabrasComando[$posicionVecina]) && in_array($palabrasComando[$posicionVecina], $palabrasClave)) {
                    $puntuacionPorProximidad += 5; // Incrementar puntuación por cercanía
                }
            }
        }
    }

    return $puntuacionPorProximidad;
}

function calcularPuntuacionPorRelevancia($palabraClave, $comandos) {
    $puntuacionPorRelevancia = 0;
    $totalComandos = count($comandos);

    foreach ($comandos as $comando => $respuesta) {
        if (strpos(normalizarTexto($comando), $palabraClave) !== false) {
            $puntuacionPorRelevancia += 1; // Incrementar puntuación si la palabra clave está en el comando
        }
    }

    // Normalizar la puntuación basada en la frecuencia de aparición
    if ($totalComandos > 0) {
        $puntuacionPorRelevancia = ($puntuacionPorRelevancia / $totalComandos) * 10; // Escalar la puntuación
    }

    return $puntuacionPorRelevancia;
}

function calcularPuntuacionPorOrden($comando, $palabrasClave) {
    $puntuacionPorOrden = 0;

    // Convertir el comando a un arreglo de palabras
    $palabrasComando = explode(' ', $comando);

    // Comparar el orden de las palabras clave en el comando
    $prevPosicion = -1;
    foreach ($palabrasClave as $palabraClave) {
        $posicionActual = array_search($palabraClave, $palabrasComando);

        if ($posicionActual !== false && $posicionActual > $prevPosicion) {
            $puntuacionPorOrden += 5;
            $prevPosicion = $posicionActual;
        } else {
            // Penalización si el orden no se mantiene
            $puntuacionPorOrden -= 5;
        }
    }

    return $puntuacionPorOrden;
}

function buscarEnComandos($comandos, $palabrasClave, $sinonimos) {
    $comandosCoincidentes = [];

    foreach ($comandos as $comando => $respuesta) {
        $puntuacionComando = 0;
        $comandoNormalizado = normalizarTexto($comando);

        foreach ($palabrasClave as $palabraClave) {
            $sinonimosPalabra = buscarSinonimos($palabraClave, $sinonimos);
            $sinonimosPalabra[] = $palabraClave; // Incluir la palabra clave en la lista de sinónimos

            foreach ($sinonimosPalabra as $sinonimo) {
                if (strpos($comandoNormalizado, $sinonimo) !== false) {
                    if ($sinonimo == $palabraClave) {
                        // Puntuación mayor para coincidencia exacta
                        $puntuacionComando += 20;
                    } else {
                        // Puntuación base por coincidencia de sinónimo
                        $puntuacionComando += 10;

                        // Puntuación adicional basada en la distancia de Levenshtein, solo si necesario
                        if ($sinonimo !== $palabraClave) {
                            $distancia = calcularDistanciaLevenshtein($sinonimo, $palabraClave);
                            $puntuacionComando += max(10 - $distancia, 0); // Puntuación adicional decrece con la distancia
                        }
                    }
                }
            }
        }

        // Añadir puntuación por proximidad y por orden de palabras
        $puntuacionComando += calcularPuntuacionPorProximidad($comandoNormalizado, $palabrasClave);
        $puntuacionComando += calcularPuntuacionPorOrden($comandoNormalizado, $palabrasClave);

        if ($puntuacionComando > 0) {
            $comandosCoincidentes[$comando] = [
                'puntuacion' => $puntuacionComando,
                'respuesta' => $respuesta
            ];
        }
    }

    return $comandosCoincidentes;
}

function calcularDistanciaLevenshtein($cadena1, $cadena2) {
    $cadena1 = normalizarTexto($cadena1); // Normaliza la cadena1
    $cadena2 = normalizarTexto($cadena2); // Normaliza la cadena2
    return levenshtein($cadena1, $cadena2);
}

function elegirMejorRespuesta($pregunta, $comandosCoincidentes) {
    $mejorRespuesta = null;
    $mejorPuntuacion = -1;
    $umbralBajaPuntuacion = 30; // Definir un umbral para baja puntuación

    foreach ($comandosCoincidentes as $comando => $datos) {
        if ($datos['puntuacion'] > $mejorPuntuacion) {
            $mejorPuntuacion = $datos['puntuacion'];
            $mejorRespuesta = $datos['respuesta'];
        }
    }

    // Verificar si la puntuación es baja y agregar un mensaje de advertencia
    if ($mejorPuntuacion < $umbralBajaPuntuacion) {
        return "Quizá no te doy la respuesta adecuada, pero: " . ($mejorRespuesta ?? "Lo siento, no puedo encontrar una respuesta adecuada para tu pregunta.");
    }

    return $mejorRespuesta ?? "Lo siento, no puedo encontrar una respuesta adecuada para tu pregunta.";
}

// Función principal para procesar la pregunta del usuario
function procesarPregunta($pregunta) {
    global $comandos, $sinonimos;

    $palabrasClave = extraerPalabrasClave($pregunta);
    $comandosCoincidentes = buscarEnComandos($comandos, $palabrasClave, $sinonimos);
    $mejorRespuesta = elegirMejorRespuesta($pregunta, $comandosCoincidentes);

    return $mejorRespuesta;
}
?>
