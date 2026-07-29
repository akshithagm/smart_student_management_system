<?php
session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $admin_id = trim($_POST["admin_id"]);
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Check empty fields
    if (
        empty($admin_id) ||
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        echo "<script>
                alert('Please fill all the fields.');
                window.location='admin_register.html';
              </script>";
        exit();
    }

    // Password match
    if ($password != $confirm_password) {
        echo "<script>
                alert('Passwords do not match.');
                window.location='admin_register.html';
              </script>";
        exit();
    }

    // Check Admin ID or Email already exists
    $check = "SELECT * FROM admins WHERE admin_id=? OR email=?";
    $stmt = mysqli_prepare($conn, $check);
    mysqli_stmt_bind_param($stmt, "ss", $admin_id, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        echo "<script>
                alert('Admin ID or Email already exists.');
                window.location='admin_register.html';
              </script>";
        exit();
    }

    // Encrypt Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert Admin
    $sql = "INSERT INTO admins
            (admin_id, full_name, email, phone, password)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $admin_id,
        $full_name,
        $email,
        $phone,
        $hashed_password
    );

    if (mysqli_stmt_execute($stmt)) {

        echo "<script>
                alert('Admin Registered Successfully.');
                window.location='admin_login.html';
              </script>";

    } else {

        echo "<script>
                alert('Registration Failed.');
                window.location='admin_register.html';
              </script>";

    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>