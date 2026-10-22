
<?php
header('Content-Type: application/json; charset=utf-8');

$apiKey = getenv('GEMINI_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gemini API key is not configured on the server.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$income = isset($input['income']) ? (float)$input['income'] : 0;
$expenses = isset($input['expenses']) ? (float)$input['expenses'] : 0;
$balance = $income - $expenses;
$categories = $input['categories'] ?? [];

if (!is_array($categories)) {
    $categories = [];
}

$prompt = "You are MoneyMate, a personal finance assistant. "
    . "Give 3 short, practical spending insights using these figures in INR. "
    . "Income: ₹" . $income
    . ", Expenses: ₹" . $expenses
    . ", Balance: ₹" . $balance
    . ", Expense categories: " . json_encode($categories)
    . ". Do not claim to be a financial adviser. "
    . "Do not invent transactions. Return plain text, not markdown.";

$payload = json_encode([
    'contents' => [[
        'parts' => [['text' => $prompt]]
    ]]
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => $payload
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Could not contact Gemini. Check your connection and PHP cURL.'
    ]);
    exit;
}

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

if ($status < 200 || $status >= 300 || !$text) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Gemini request failed. Check API quota, model availability and server configuration.',
        'status' => $status
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'insights' => $text
]);
