<?php
$access_enabled = file_get_contents('access_enabled.txt');

if ($access_enabled === 'true') {
    $url_telescopio = "http://www.PaginaDePruebasPeroQueSeriaLaRealDelTelescopio.com"; // Seguramente esto no sirve y hay que hacer algo para telescopios con rtsp
    echo "<html>
            <head>
                <title>Visualización del Telescopio</title>
                <style>
                    body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; }
                    iframe { width: 100%; height: 100%; border: none; }
                </style>
            </head>
            <body>
                <iframe src='$url_telescopio'></iframe>
            </body>
          </html>";
} else {
    $url_simulacion = "https://www.MiWeb.com/Telescopio/index.html";
    echo "<html>
            <head>
                <title>Acceso Deshabilitado</title>
                <script>
                    setTimeout(function() {
                        window.location.href = '$url_simulacion';
                    }, 10000); // Redirecciona después de 10 segundos
                </script>
            </head>
            <body>
                <p>El acceso a la visualización del telescopio está actualmente deshabilitado. ⏳ Activaremos esta función en el momento adecuado de cada observación. ¡Gracias por tu comprensión! 😉</p>
            </body>
          </html>";
}
?>
