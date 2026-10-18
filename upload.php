<?php
/**
 * APK Store - Upload handler
 * Saves uploaded .apk files to the /uploads folder on the server.
 */

// ---------- Configuration ----------
$uploadDir   = __DIR__ . '/uploads/';   // Folder where APKs are stored
$maxSize     = 500 * 1024 * 1024;      // 500 MB max
$allowedExt  = ['apk'];
$allowedMime = [
    'application/vnd.android.package-archive',
    'application/octet-stream',
    'application/java-archive',
    'application/zip'
];

// ---------- CORS (needed if HTML is on another domain) ----------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- Only accept POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ---------- Check file was sent ----------
if (!isset($_FILES['apk']) || $_FILES['apk']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['apk'];

// ---------- Check for upload errors ----------
if ($file['error'] !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];
    $msg = $messages[$file['error']] ?? 'Unknown upload error.';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ---------- Size check ----------
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File is too large. Max 500 MB.']);
    exit;
}

// ---------- Extension check ----------
$originalName = basename($file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only .apk files are allowed.']);
    exit;
}

// ---------- Create uploads folder if needed ----------
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cannot create upload directory.']);
        exit;
    }
}

// ---------- Generate safe unique filename ----------
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$safeName = substr($safeName, 0, 80); // limit length
$uniqueName = $safeName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.apk';
$destination = $uploadDir . $uniqueName;

// ---------- Move file ----------
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file on server.']);
    exit;
}

// ---------- Success ----------
echo json_encode([
    'success'  => true,
    'message'  => 'APK uploaded and saved to server.',
    'filename' => $uniqueName,
    'original' => $originalName,
    'size'     => $file['size'],
    'url'      => 'uploads/' . $uniqueName
]);
