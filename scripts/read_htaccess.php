<?php
\ = __DIR__ . '/../.htaccess';
if (is_readable(\)) {
    echo json_encode(['status' => 'ok', 'content' => file_get_contents(\)]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Cannot read']);
}

