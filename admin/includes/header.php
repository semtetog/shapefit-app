<?php
// admin/includes/header.php (VERSÃO FINAL CORRIGIDA)

// 1. Inclui o config.php principal PRIMEIRO para ter acesso às constantes.
require_once __DIR__ . '/../../includes/config.php';

// 2. Inicia a sessão se ainda não foi iniciada.
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 3. Agora podemos definir BASE_ADMIN_URL com segurança.
if (!defined('BASE_ADMIN_URL')) {
    define('BASE_ADMIN_URL', BASE_APP_URL . '/admin'); // Usa BASE_APP_URL do seu config principal
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Painel Admin ShapeFIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Agora os caminhos usarão a constante corretamente -->
    <link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/admin_style.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo BASE_ADMIN_URL; ?>/dashboard.php" class="logo">ShapeFIT</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo ($page_slug ?? '') === 'dashboard' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'users' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/users.php"><i class="fas fa-users"></i> Pacientes</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'recipes' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/recipes.php"><i class="fas fa-utensils"></i> Receitas</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'plans' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/plans.php"><i class="fas fa-file-invoice-dollar"></i> Planos</a>
                    </li>
                      <li class="<?php if ($page_slug === 'ranks') echo 'active'; ?>">
            <a href="ranks.php"><i class="fas fa-trophy"></i> Ranking</a>
        </li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <div class="user-info">
                    <span>Olá, <strong><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!</strong></span>
                    <small>ShapeFIT</small>
                </div>
                <div class="header-actions">
                    <a href="<?php echo BASE_ADMIN_URL; ?>/logout.php" title="Sair"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </header>
            <div class="content-wrapper">