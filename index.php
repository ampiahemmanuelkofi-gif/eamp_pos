<?php
require_once 'config/constants.php';
require_once 'includes/Router.php';

// Controllers
require_once 'includes/controllers/DashboardController.php';

$router = new Router();

// Define Routes
$router->add('GET', 'dashboard', [new DashboardController(), 'index']);
$router->add('GET', '', function () {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
});
$router->add('GET', 'index.php', function () {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
});

$router->dispatch($_SERVER['REQUEST_URI']);
?>