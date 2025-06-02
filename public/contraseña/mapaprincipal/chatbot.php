<?php
session_start(); // 🔹 Necesario para guardar historial

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

$userMessage = trim($_POST['message'] ?? '');
if ($userMessage === '') {
    echo "Por favor escribe algo.";
    exit;
}

// API Key (reemplaza con tu clave real)
$apiKey = 'AIzaSyBWErSzzN6m5Mdtgnj6MlrG1Z52bFszoyk';
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

// Iniciar historial si no existe
if (!isset($_SESSION['historial'])) {
    $_SESSION['historial'] = [];
}

// Añadir nuevo mensaje del usuario al historial
$_SESSION['historial'][] = [
    "role" => "user",
    "parts" => [[ "text" => $userMessage ]]
];

// Convertir historial completo (con role) al formato correcto
$contents = [];
foreach ($_SESSION['historial'] as $mensaje) {
    $contents[] = [
        "role" => $mensaje["role"],
        "parts" => $mensaje["parts"]
    ];
}

// Añadir instrucción para limitar la respuesta del último mensaje
$ultimaIndex = count($contents) - 1;
if ($contents[$ultimaIndex]['role'] === 'user') {
    $original = $contents[$ultimaIndex]["parts"][0]["text"];
    $contents[$ultimaIndex]["parts"][0]["text"] = "Responde con máximo 60 palabras. " . $original;
}

// Preparar JSON
$data = [ "contents" => $contents ];
$jsonData = json_encode($data);

// Enviar a la API
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if (!$response) {
    echo "Error en cURL: $error";
    exit;
}

$data = json_decode($response, true);
$generated = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$generated) {
    echo "La IA no respondió correctamente.<br><pre>$response</pre>";
    exit;
}

// Guardar respuesta de la IA en el historial
$_SESSION['historial'][] = [
    "role" => "model",
    "parts" => [[ "text" => $generated ]]
];

// Eliminar asteriscos y mostrar
$cleaned = str_replace('*', '', $generated);
echo $cleaned;
