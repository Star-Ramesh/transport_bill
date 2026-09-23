<?php
/* =========================================================
   ALWAYS START FRESH — destroy any existing session
   on visiting login.php
   ========================================================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If a session exists, wipe it
if (!empty($_SESSION)) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    // Start a fresh session for the login form
    session_start();
}

include 'constant.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $username_safe = mysqli_real_escape_string($conn, $username);
    $query = "SELECT id, username, password, full_name, branch_id, role_id, active
              FROM `user`
              WHERE username = '$username_safe'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("SQL Error: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) === 1) {

        $row             = mysqli_fetch_assoc($result);
        $user_id         = $row['id'];
        $stored_password = $row['password'];
        $isActive        = (int) $row['active'];

        // Check active status
        if ($isActive !== 1) {
            $error = "Your account has been disabled. Contact the administrator.";
        }
        // 🔓 Plain-text password match (for demo only — will be hashed later)
        elseif ($password === $stored_password) {

            // Prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user_id;
            $_SESSION['username']  = $row['username'];
            $_SESSION['full_name'] = $row['full_name'] ?: $row['username'];
            $_SESSION['branch_id'] = (int) ($row['branch_id'] ?? 0);
            $_SESSION['role_id']   = (int) ($row['role_id']   ?? 0);

            // Load permissions for this role
            $permissions = [];
            if ($_SESSION['role_id'] > 0) {
                $roleId = (int) $_SESSION['role_id'];
                $permRes = mysqli_query(
                    $conn,
                    "SELECT p.perm_key
                     FROM role_permission rp
                     JOIN permission p ON p.id = rp.permission_id
                     WHERE rp.role_id = $roleId"
                );
                if ($permRes) {
                    while ($p = mysqli_fetch_assoc($permRes)) {
                        $permissions[] = $p['perm_key'];
                    }
                }
            }
            $_SESSION['permissions'] = $permissions;

            header("Location: index.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Username not found.";
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Login | Transport Billing</title>

    <!-- Font Awesome -->
    <link
        href="vendor/fontawesome-free/css/all.min.css"
        rel="stylesheet"
        type="text/css">

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,700,900"
        rel="stylesheet">

    <!-- SB Admin 2 -->
    <link
        href="css/main.min.css"
        rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
        }

        .login-card {
            height: 500px;
            border-radius: 15px;
            overflow: hidden;
        }

        .login-row {
            height: 500px;
            display: flex;
            align-items: stretch;
        }

        .login-image {
            height: 500px;
            background: #3da3e8;
            padding: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-logo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .login-form-column {
            height: 500px;
            overflow: hidden;
            display: flex;
            align-items: flex-start;
        }

        .login-form-content {
            width: 100%;
        }

        .login-error {
            width: 100%;
            margin-bottom: 1rem;
        }

        .brand-icon {
            font-size: 45px;
        }

        .brand-title {
            font-weight: 700;
            letter-spacing: 1px;
        }

        .login-subtitle {
            font-size: 13px;
        }

        .login-btn {
            border-radius: 50px;
            font-weight: 700;
            padding: 12px;
        }

        .form-control-user {
            height: 50px;
        }

        .show-password {
            cursor: pointer;
        }

        @media (max-width: 991px) {
            .login-card {
                height: auto;
                margin-left: 15px;
                margin-right: 15px;
            }

            .login-row {
                height: auto;
                display: block;
            }

            .login-image {
                display: none;
            }

            .login-form-column {
                width: 100%;
                height: auto;
                overflow: visible;
            }
        }
    </style>

</head>

<body class="bg-gradient-primary">

    <div class="container">

        <div
            class="row justify-content-center align-items-center"
            style="min-height: 100vh;">

            <div class="col-xl-10 col-lg-11 col-md-10">

                <div class="card login-card border-0 shadow-lg">

                    <div class="card-body p-0">

                        <div class="row login-row">

                            <!-- LEFT IMAGE -->
                            <div class="col-lg-6 login-image">

                                <img
                                    src="img/istockphoto-472109275-1024x1024.jpg"
                                    class="login-logo"
                                    alt="Transport Truck">

                            </div>

                            <!-- RIGHT FORM -->
                            <div class="col-lg-6 login-form-column">

                                <div class="p-5 login-form-content">

                                    <div class="text-center mb-4">

                                        <div class="brand-icon text-primary mb-2">
                                            <i class="fas fa-truck"></i>
                                        </div>

                                        <h1 class="h4 text-gray-900 brand-title">
                                            Transport Billing
                                        </h1>

                                        <p class="text-muted login-subtitle mb-0">
                                            Sign in to continue
                                        </p>

                                    </div>

                                    <!-- ERROR -->
                                    <?php if ($error): ?>
                                        <div
                                            class="alert alert-danger alert-dismissible fade show login-error"
                                            role="alert">

                                            <i class="fas fa-exclamation-circle mr-2"></i>

                                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

                                            <button
                                                type="button"
                                                class="close"
                                                data-dismiss="alert"
                                                aria-label="Close">
                                                <span>&times;</span>
                                            </button>

                                        </div>
                                    <?php endif; ?>

                                    <!-- FORM -->
                                    <form
                                        class="user"
                                        method="POST"
                                        action="">

                                        <div class="form-group">
                                            <div class="input-group">

                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <i class="fas fa-user"></i>
                                                    </span>
                                                </div>

                                                <input
                                                    type="text"
                                                    name="username"
                                                    class="form-control"
                                                    placeholder="Username"
                                                    autocomplete="username"
                                                    required>

                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="input-group">

                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                </div>

                                                <input
                                                    type="password"
                                                    name="password"
                                                    class="form-control"
                                                    id="passwordInput"
                                                    placeholder="Password"
                                                    autocomplete="current-password"
                                                    required>

                                            </div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <div class="custom-control custom-checkbox small">

                                                <input
                                                    type="checkbox"
                                                    class="custom-control-input"
                                                    id="showPasswordCheck"
                                                    onclick="togglePasswordCheck()">

                                                <label
                                                    class="custom-control-label show-password"
                                                    for="showPasswordCheck">

                                                    Show Password

                                                </label>

                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-user btn-block login-btn">

                                            <i class="fas fa-sign-in-alt mr-2"></i>
                                            Login

                                        </button>

                                    </form>

                                    <div class="text-center mt-4">
                                        <small class="text-muted">
                                            Transport Billing System
                                        </small>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/main.min.js"></script>

    <script>
        function togglePasswordCheck() {
            var input = document.getElementById("passwordInput");
            var checkbox = document.getElementById("showPasswordCheck");
            input.type = checkbox.checked ? "text" : "password";
        }
    </script>

</body>

</html>