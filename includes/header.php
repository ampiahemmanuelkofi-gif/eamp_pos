<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/settings_loader.php';
require_once __DIR__ . '/licensing_helper.php';

// Global License Check
if (!is_system_authorized()) {
    header("Location: " . BASE_URL . "/modules/auth/activate.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const SETTINGS = <?php echo json_encode($SYSTEM_SETTINGS ?? []); ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/assets/js/autologout.js?v=<?php echo time(); ?>"></script>
    <style>
        :root {
            --primary-color:
                <?php echo $SYSTEM_SETTINGS['theme_color'] ?? '#0d6efd'; ?>
            ;
        }

        /* Override Bootstrap Primary */
        .btn-primary,
        .bg-primary,
        .badge.bg-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .btn-outline-primary {
            color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color) !important;
            color: white !important;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            min-height: 100vh;
            background: #2b3035 !important;
            color: white;
        }

        .sidebar a {
            color: rgba(255, 255, 255, .8);
            text-decoration: none;
            padding: 10px 20px;
            display: block;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--primary-color) !important;
            color: white;
        }

        .main-content {
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php include_once __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>
        <div class="flex-grow-1">
            <?php if (isset($_SESSION['user_id'])): ?>
                <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4">
                    <span class="navbar-brand d-flex align-items-center">
                        <?php if (!empty($SYSTEM_SETTINGS['company_logo'])): ?>
                            <img src="<?php echo BASE_URL . '/' . $SYSTEM_SETTINGS['company_logo']; ?>" alt="Logo"
                                class="me-2 rounded" style="height: 30px; width: auto;">
                        <?php endif; ?>
                        <?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?>
                    </span>
                    <div class="ms-auto">
                        <span class="me-3">Welcome,
                            <?php echo $_SESSION['username'] ?? 'User'; ?>
                        </span>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/profile.php"
                            class="btn btn-outline-primary btn-sm me-2">Profile</a>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php"
                            class="btn btn-outline-danger btn-sm">Logout</a>
                    </div>
                </nav>
            <?php endif; ?>
            <div class="main-content">