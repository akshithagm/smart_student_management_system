<?php
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $year = mysqli_real_escape_string($conn, $_POST['year']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    if ($password != $confirm_password) {
        die("Passwords do not match!");
    }

    // Check Email
    $check = mysqli_query($conn, "SELECT * FROM students WHERE email='$email'");

    if (mysqli_num_rows($check) > 0) {
        die("Email already registered!");
    }

    // Generate Student ID
    $result = mysqli_query($conn, "SELECT id FROM students ORDER BY id DESC LIMIT 1");

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $next = $row['id'] + 1;
    } else {
        $next = 1;
    }

    $student_id = "STU" . str_pad($next, 3, "0", STR_PAD_LEFT);

    // Save Student
    $sql = "INSERT INTO students
    (student_id, full_name, email, phone, department, year, password)
    VALUES
    ('$student_id','$full_name','$email','$phone','$department','$year','$password')";

    if (mysqli_query($conn, $sql)) {

        echo "<script>
        alert('Registration Successful!\\nYour Student ID is: $student_id');
        window.location='student_login.html';
        </script>";

    } else {

        echo "Registration Failed.";

    }

}
?>