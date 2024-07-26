<?php
// Obtiene el token del bot de un archivo externo para mayor seguridad
$botToken = trim(file_get_contents('token.txt'));  // Asegúrate de que la ruta al archivo es correcta

// URL base para las llamadas API de Telegram
$website = "https://api.telegram.org/bot" . $botToken;

// Define la URL a tu script bot.php donde Telegram enviará las actualizaciones
$webhookUrl = "https://www.SupongoQueAquiSePoneAstroturismoLaEstacion.com/Astrobot/bot.php";  

// Realiza una solicitud HTTP POST para configurar el webhook
$response = file_get_contents($website . "/setWebhook?url=" . urlencode($webhookUrl));

// Opcional: Imprime la respuesta de Telegram para verificar que el webhook fue configurado correctamente
echo $response;
?>
