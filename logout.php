<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_post();
logout_user();
session_start();
flash('success', 'You are signed out.');
redirect(url('index.php'));
