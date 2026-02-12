<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/settings_loader.php';
require_once '../../includes/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed");
    }
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password']; // Password might be hashed

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        // Check in user table first (for admin/top-level)
        $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // In a real app, use password_verify. For this MVP/Legacy style, 
            // the user schema shows just 'password'. Assuming plaintext for now 
            // as per common legacy php projects, or verify if hashed.
            // If the user provided 'password' is plaintext in DB:
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = 'admin'; // Assuming 'user' table is for admins
                $_SESSION['shop_id'] = $user['shop'] ?? null;
                header("Location: " . BASE_URL . "/modules/dashboard/index.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            // Check staff_list table
            $stmt = $conn->prepare("SELECT * FROM staff_list WHERE staff_id = ?"); // Assuming login with staff_id or staff_name?
            /* 
               The prompt example used `user` and `staff_list`. 
               Usually staff login with maybe ID or Name. 
               Let's try staff_name or staff_id. The table has `staff_id`, `staff_name`, `password`.
               Let's assume username input can be staff_name or staff_id.
            */
            $stmt = $conn->prepare("SELECT * FROM staff_list WHERE staff_name = ? OR staff_id = ?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['staff_name'];
                    $_SESSION['user_role'] = $user['rank']; // rank = cashier/manager
                    $_SESSION['shop_id'] = $user['shop'];
                    header("Location: " . BASE_URL . "/modules/dashboard/index.php");
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login -
        <?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            background-image: url('../../assets/images/bk.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 24px;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="brand-logo">
            <?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Username / Staff ID</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>

</html>