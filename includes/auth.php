<?php
// includes/auth.php

function startSess() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSess();
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

function currentUser() {
    startSess();
    return $_SESSION ?? [];
}

function hasRole($r) {
    startSess();

    if (!isset($_SESSION['role'])) {
        return false;
    }

    return in_array($_SESSION['role'], (array) $r);
}

function isGudang() {
    startSess();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'gudang';
}

function requireOwner() {
    requireLogin();
    if (isGudang()) {
        header('Location: ' . BASE_URL . '/barang_masuk.php');
        exit();
    }
}

function logout() {
    startSess();
    session_destroy();

    header('Location: ' . BASE_URL . '/login.php');
    exit();
}