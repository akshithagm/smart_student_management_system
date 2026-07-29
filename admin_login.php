<?php

session_start();

include "db_connect.php";

/*
|--------------------------------------------------------------------------
| Allow only POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin_login.html");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get and clean form data
|--------------------------------------------------------------------------
*/

$admin_email = trim($_POST["admin_email"] ?? "");
$password = $_POST["password"] ?? "";

/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if ($admin_email === "" || $password === "") {
    echo "
        <script>
            alert('Please enter both email and password.');
            window.location.href = 'admin_login.html';
        </script>
    ";
    exit();
}

if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
    echo "
        <script>
            alert('Please enter a valid email address.');
            window.location.href = 'admin_login.html';
        </script>
    ";
    exit();
}

/*
|--------------------------------------------------------------------------
| Find admin using email
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        admin_id,
        full_name,
        email,
        phone,
        password
    FROM admins
    WHERE email = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $admin_email);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

/*
|--------------------------------------------------------------------------
| Check whether admin exists
|--------------------------------------------------------------------------
*/

if (mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($stmt);

    echo "
        <script>
            alert('Admin account not found.');
            window.location.href = 'admin_login.html';
        </script>
    ";
    exit();
}

$admin = mysqli_fetch_assoc($result);

/*
|--------------------------------------------------------------------------
| Verify encrypted password
|--------------------------------------------------------------------------
*/

if (!password_verify($password, $admin["password"])) {
    mysqli_stmt_close($stmt);

    echo "
        <script>
            alert('Incorrect admin password.');
            window.location.href = 'admin_login.html';
        </script>
    ";
    exit();
}

/*
|--------------------------------------------------------------------------
| Regenerate session ID for security
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

/*
|--------------------------------------------------------------------------
| Store admin details in session
|--------------------------------------------------------------------------
*/

$_SESSION["admin_logged_in"] = true;
$_SESSION["admin_database_id"] = $admin["id"];
$_SESSION["admin_id"] = $admin["admin_id"];
$_SESSION["admin_name"] = $admin["full_name"];
$_SESSION["admin_email"] = $admin["email"];
$_SESSION["admin_phone"] = $admin["phone"];

/*
|--------------------------------------------------------------------------
| Close statement and redirect
|--------------------------------------------------------------------------
*/

mysqli_stmt_close($stmt);

header("Location: admin_dashboard.php");
exit();

?>