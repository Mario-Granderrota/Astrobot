<?php
require 'funcionesLenguaje.php';

function ejecutarTest($condition, $testName, $errorMessage, $resultados = null) {
    echo "Ejecutando {$testName}...\n";
    if (!$condition) {
        echo "ERROR en {$testName}: {$errorMessage}\n";
        if ($resultados !== null) {
            echo "Resultados obtenidos: " . print_r($resultados, true) . "\n";
        }
        return false;
    } else {
        echo "{$testName} pasó correctamente.\n";
        return true;
    }
}

function testObtenerComandos() {
    $comandos = obtenerComandos();
    return ejecutarTest(!empty($comandos), "testObtenerComandos", "La función obtenerComandos no devolvió resultados.");
}

function testObtenerSinonimos() {
    $sinonimos = obtenerSinonimos();
    return ejecutarTest(!empty($sinonimos), "testObtenerSinonimos", "La función obtenerSinonimos no devolvió resultados.");
}

function testExtraerPalabrasClave() {
    $texto = "¿Cómo está la temperatura hoy?";
    $resultadoEsperado = ["como", "temperatura", "hoy"]; // Ajustado según la lógica de normalización y stopwords
    $palabrasClave = extraerPalabrasClave($texto);
    return ejecutarTest($palabrasClave == $resultadoEsperado, "testExtraerPalabrasClave", "La función extraerPalabrasClave no funcionó como se esperaba.", $palabrasClave);
}

function testBuscarSinonimos() {
    $sinonimos = obtenerSinonimos();
    $palabra = "calor";
    $sinonimosDeCalor = buscarSinonimos($palabra, $sinonimos);
    return ejecutarTest(in_array("calor", $sinonimosDeCalor), "testBuscarSinonimos", "La función buscarSinonimos no incluyó la palabra original en los resultados.", $sinonimosDeCalor);
}

function testBuscarEnComandos() {
    $comandos = obtenerComandos();
    $sinonimos = obtenerSinonimos();
    $palabrasClave = ["temperatura"];
    $resultados = buscarEnComandos($comandos, $palabrasClave, $sinonimos);
    return ejecutarTest(!empty($resultados), "testBuscarEnComandos", "La función buscarEnComandos no devolvió resultados.", $resultados);
}

function ejecutarPruebas() {
    $resultados = [];
    $resultados[] = testObtenerComandos();
    $resultados[] = testObtenerSinonimos();
    $resultados[] = testExtraerPalabrasClave();
    $resultados[] = testBuscarSinonimos();
    $resultados[] = testBuscarEnComandos();

    if (in_array(false, $resultados, true)) {
        echo "Algunas pruebas fallaron.\n";
    } else {
        echo "Todas las pruebas se ejecutaron correctamente.\n";
    }
}

ejecutarPruebas();
?>
