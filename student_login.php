<?php
session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = mysqli_real_escape_string(
        $conn,
        trim($_POST["student_id"])
    );

    $password = $_POST["password"];

    $sql = "SELECT * FROM students
            WHERE student_id = '$student_id'
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("Login query failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) === 1) {

        $student = mysqli_fetch_assoc($result);

        /*
          The current registration code stores plain-text passwords,
          so this comparison matches that setup.
        */
        if ($password === $student["password"]) {

            $_SESSION["student_id"] = $student["student_id"];
            $_SESSION["student_database_id"] = $student["id"];
            $_SESSION["student_name"] = $student["full_name"];

            header("Location: student_dashboard.php");
            exit();

        } else {

            echo "
                <script>
                    alert('Incorrect password.');
                    window.location.href = 'student_login.html';
                </script>
            ";
            exit();
        }

    } else {

        echo "
            <script>
                alert('Student ID not found.');
                window.location.href = 'student_login.html';
            </script>
        ";
        exit();
    }
}

header("Location: student_login.html");
exit();
?>