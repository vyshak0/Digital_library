<?php
// ---------- Database (XAMPP defaults; change if your MySQL differs) ----------
define('DB_HOST', getenv('DL_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DL_DB_USER') ?: 'root');
define('DB_PASS', getenv('DL_DB_PASS') !== false ? getenv('DL_DB_PASS') : '');
define('DB_NAME', getenv('DL_DB_NAME') ?: 'digital_library');
define('DB_PORT', (int) (getenv('DL_DB_PORT') ?: 3306));   // use 3307 if you moved XAMPP's MySQL (see README)

// ---------- Library rules ----------
const LOAN_DAYS        = 14;   // how long a loan lasts
const MAX_ACTIVE_LOANS = 3;    // books one reader can hold at once
const MAX_UPLOAD_MB    = 20;   // size limit for PDF / EPUB uploads
const BOOKS_PER_PAGE   = 12;

const UPLOAD_DIR = __DIR__ . '/uploads';

// ---------- URL prefix ----------
// Detected automatically (e.g. "/digital-library" when the folder sits in
// htdocs). Set DL_BASE_URL or edit this line if links come out wrong.
$__root = str_replace('\\', '/', (string) realpath(__DIR__));
$__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$__base = ($__doc !== '' && strncasecmp($__root, $__doc, strlen($__doc)) === 0)
    ? substr($__root, strlen($__doc)) : '';
define('BASE_URL', getenv('DL_BASE_URL') !== false ? rtrim(getenv('DL_BASE_URL'), '/') : rtrim($__base, '/'));
unset($__root, $__doc, $__base);
