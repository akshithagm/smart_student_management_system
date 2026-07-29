<?php

session_start();
include "db_connect.php";

/* ==================================================
   ADMIN SESSION CHECK
================================================== */

if (
    !isset($_SESSION["admin_logged_in"]) ||
    $_SESSION["admin_logged_in"] !== true
) {
    header("Location: admin_login.html");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";
$admin_id   = $_SESSION["admin_id"] ?? "Admin";

$message = "";
$messageType = "";

/* ==================================================
   ADD STUDENT
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = strtoupper(
        trim($_POST["student_id"] ?? "")
    );

    $full_name = trim($_POST["full_name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $phone = trim($_POST["phone"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* Empty-field validation */

    if (
        $student_id === "" ||
        $full_name === "" ||
        $email === "" ||
        $phone === "" ||
        $department === "" ||
        $year === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $message = "Please fill in all the fields.";
        $messageType = "error";
    }

    /* Email validation */

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    }

    /* Phone validation */

    elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
        $message = "Phone number must contain exactly 10 digits.";
        $messageType = "error";
    }

    /* Password length validation */

    elseif (strlen($password) < 6) {
        $message = "Password must contain at least 6 characters.";
        $messageType = "error";
    }

    /* Password confirmation */

    elseif ($password !== $confirm_password) {
        $message = "Password and confirm password do not match.";
        $messageType = "error";
    }

    else {

        /* Check duplicate Student ID or email */

        $checkSql = "
            SELECT id
            FROM students
            WHERE student_id = ?
               OR email = ?
            LIMIT 1
        ";

        $checkStmt = mysqli_prepare($conn, $checkSql);

        if (!$checkStmt) {
            $message = "Unable to prepare the database query.";
            $messageType = "error";
        } else {

            mysqli_stmt_bind_param(
                $checkStmt,
                "ss",
                $student_id,
                $email
            );

            mysqli_stmt_execute($checkStmt);

            $checkResult =
                mysqli_stmt_get_result($checkStmt);

            if (mysqli_num_rows($checkResult) > 0) {

                $message =
                    "Student ID or email address already exists.";

                $messageType = "error";

            } else {

                /* Encrypt password */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /* Insert student */

                $insertSql = "
                    INSERT INTO students
                    (
                        student_id,
                        full_name,
                        email,
                        phone,
                        department,
                        year,
                        password
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";

                $insertStmt =
                    mysqli_prepare($conn, $insertSql);

                if (!$insertStmt) {

                    $message =
                        "Unable to prepare the insert query.";

                    $messageType = "error";

                } else {

                    mysqli_stmt_bind_param(
                        $insertStmt,
                        "sssssss",
                        $student_id,
                        $full_name,
                        $email,
                        $phone,
                        $department,
                        $year,
                        $hashed_password
                    );

                    if (mysqli_stmt_execute($insertStmt)) {

                        mysqli_stmt_close($insertStmt);
                        mysqli_stmt_close($checkStmt);

                        echo "
                            <script>
                                alert(
                                    'Student added successfully.'
                                );

                                window.location.href =
                                    'manage_students.php';
                            </script>
                        ";

                        exit();

                    } else {

                        $message =
                            "Student could not be added. Please try again.";

                        $messageType = "error";
                    }

                    mysqli_stmt_close($insertStmt);
                }
            }

            mysqli_stmt_close($checkStmt);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Student | SSMS</title>

    <link rel="stylesheet" href="style.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body class="admin-dashboard-page">

    <aside class="admin-sidebar">

        <div class="admin-sidebar-brand">

            <div class="admin-sidebar-logo">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>

            <div>
                <h2>SSMS</h2>
                <span>Admin Portal</span>
            </div>

        </div>

        <div class="admin-sidebar-profile">

            <div class="admin-profile-avatar">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>

                <h3>
                    <?php
                    echo htmlspecialchars($admin_name);
                    ?>
                </h3>

                <p>
                    <?php
                    echo htmlspecialchars($admin_id);
                    ?>
                </p>

            </div>

        </div>

        <nav class="admin-sidebar-menu">

            <a href="admin_dashboard.php">

                <i class="fa-solid fa-chart-pie"></i>

                <span>Dashboard</span>

            </a>

            <a
                href="manage_students.php"
                class="active"
            >

                <i class="fa-solid fa-user-graduate"></i>

                <span>Manage Students</span>

            </a>

            <a href="manage_attendance.php">

                <i class="fa-solid fa-calendar-check"></i>

                <span>Attendance</span>

            </a>

            <a href="manage_marks.php">

                <i class="fa-solid fa-square-poll-vertical"></i>

                <span>Internal Marks</span>

            </a>

            <a href="manage_rooms.php">

                <i class="fa-solid fa-building"></i>

                <span>Manage Rooms</span>

            </a>

            <a href="manage_room_allocation.php">

                <i class="fa-solid fa-door-open"></i>

                <span>Room Allocation</span>

            </a>

        </nav>

        <div class="admin-sidebar-bottom">

            <a href="index.php">

                <i class="fa-solid fa-house"></i>

                <span>Home Page</span>

            </a>

            <a
                href="logout.php"
                class="admin-logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                <span>Logout</span>

            </a>

        </div>

    </aside>

    <main class="admin-main-content">

        <header class="add-student-header">

            <div>

                <p class="admin-header-label">
                    Student Management
                </p>

                <h1>Add New Student</h1>

                <span>
                    Create a new student account and login credentials.
                </span>

            </div>

            <a
                href="manage_students.php"
                class="add-student-back-btn"
            >

                <i class="fa-solid fa-arrow-left"></i>

                Back to Students

            </a>

        </header>

        <section class="add-student-layout">

            <div class="add-student-form-card">

                <div class="add-student-card-heading">

                    <div class="add-student-heading-icon">

                        <i class="fa-solid fa-user-plus"></i>

                    </div>

                    <div>

                        <p>Student Registration</p>

                        <h2>Student Information</h2>

                        <span>
                            Enter accurate academic and contact details.
                        </span>

                    </div>

                </div>

                <?php if ($message !== ""): ?>

                    <div
                        class="add-student-message <?php
                        echo $messageType === "error"
                            ? "add-message-error"
                            : "add-message-success";
                        ?>"
                    >

                        <i class="<?php
                        echo $messageType === "error"
                            ? "fa-solid fa-circle-exclamation"
                            : "fa-solid fa-circle-check";
                        ?>"></i>

                        <?php echo htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>

                <form
                    action="add_student.php"
                    method="POST"
                    class="add-student-form"
                    id="addStudentForm"
                >

                    <div class="add-student-form-row">

                        <div class="add-student-form-group">

                            <label for="student_id">
                                Student ID
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-id-card"></i>

                                <input
                                    type="text"
                                    id="student_id"
                                    name="student_id"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $_POST["student_id"] ?? ""
                                    );
                                    ?>"
                                    placeholder="Example: STU005"
                                    maxlength="20"
                                    required
                                >

                            </div>

                        </div>

                        <div class="add-student-form-group">

                            <label for="full_name">
                                Full Name
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-user"></i>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $_POST["full_name"] ?? ""
                                    );
                                    ?>"
                                    placeholder="Enter student's full name"
                                    maxlength="100"
                                    required
                                >

                            </div>

                        </div>

                    </div>

                    <div class="add-student-form-row">

                        <div class="add-student-form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-envelope"></i>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $_POST["email"] ?? ""
                                    );
                                    ?>"
                                    placeholder="Enter student email"
                                    maxlength="100"
                                    required
                                >

                            </div>

                        </div>

                        <div class="add-student-form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-phone"></i>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $_POST["phone"] ?? ""
                                    );
                                    ?>"
                                    placeholder="Enter 10-digit number"
                                    maxlength="10"
                                    pattern="[0-9]{10}"
                                    required
                                >

                            </div>

                        </div>

                    </div>

                    <div class="add-student-form-row">

                        <div class="add-student-form-group">

                            <label for="department">
                                Department
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-building-columns"></i>

                                <input
                                    type="text"
                                    id="department"
                                    name="department"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $_POST["department"] ?? ""
                                    );
                                    ?>"
                                    placeholder="Example: BCA"
                                    maxlength="100"
                                    required
                                >

                            </div>

                        </div>

                        <div class="add-student-form-group">

                            <label for="year">
                                Academic Year
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-calendar"></i>

                                <select
                                    id="year"
                                    name="year"
                                    required
                                >

                                    <option value="">
                                        Select academic year
                                    </option>

                                    <option
                                        value="1st Year"
                                        <?php
                                        echo (
                                            ($_POST["year"] ?? "") ===
                                            "1st Year"
                                        ) ? "selected" : "";
                                        ?>
                                    >
                                        1st Year
                                    </option>

                                    <option
                                        value="2nd Year"
                                        <?php
                                        echo (
                                            ($_POST["year"] ?? "") ===
                                            "2nd Year"
                                        ) ? "selected" : "";
                                        ?>
                                    >
                                        2nd Year
                                    </option>

                                    <option
                                        value="3rd Year"
                                        <?php
                                        echo (
                                            ($_POST["year"] ?? "") ===
                                            "3rd Year"
                                        ) ? "selected" : "";
                                        ?>
                                    >
                                        3rd Year
                                    </option>

                                    <option
                                        value="4th Year"
                                        <?php
                                        echo (
                                            ($_POST["year"] ?? "") ===
                                            "4th Year"
                                        ) ? "selected" : "";
                                        ?>
                                    >
                                        4th Year
                                    </option>

                                </select>

                            </div>

                        </div>

                    </div>

                    <div class="add-student-form-row">

                        <div class="add-student-form-group">

                            <label for="password">
                                Password
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-lock"></i>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Create login password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="add-password-toggle"
                                    id="passwordToggle"
                                    aria-label="Show or hide password"
                                >

                                    <i
                                        class="fa-solid fa-eye"
                                        id="passwordIcon"
                                    ></i>

                                </button>

                            </div>

                        </div>

                        <div class="add-student-form-group">

                            <label for="confirm_password">
                                Confirm Password
                            </label>

                            <div class="add-student-input-box">

                                <i class="fa-solid fa-shield-halved"></i>

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Re-enter the password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="add-password-toggle"
                                    id="confirmPasswordToggle"
                                    aria-label="Show or hide password"
                                >

                                    <i
                                        class="fa-solid fa-eye"
                                        id="confirmPasswordIcon"
                                    ></i>

                                </button>

                            </div>

                        </div>

                    </div>

                    <div
                        class="add-password-message"
                        id="passwordMessage"
                    ></div>

                    <div class="add-student-form-actions">

                        <a href="manage_students.php">

                            <i class="fa-solid fa-xmark"></i>

                            Cancel

                        </a>

                        <button type="submit">

                            <i class="fa-solid fa-user-plus"></i>

                            Add Student

                        </button>

                    </div>

                </form>

            </div>

            <aside class="add-student-information-card">

                <div class="add-info-main-icon">

                    <i class="fa-solid fa-circle-info"></i>

                </div>

                <h2>Account Information</h2>

                <p>
                    The student can use the Student ID and password
                    created here to access the student portal.
                </p>

                <div class="add-info-list">

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Student ID and email must be unique.
                        </span>

                    </div>

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Password is securely encrypted.
                        </span>

                    </div>

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Student appears immediately in Manage Students.
                        </span>

                    </div>

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Attendance and marks can be added later.
                        </span>

                    </div>

                </div>

            </aside>

        </section>

    </main>

    <script>

        const passwordInput =
            document.getElementById("password");

        const confirmPasswordInput =
            document.getElementById("confirm_password");

        const passwordToggle =
            document.getElementById("passwordToggle");

        const confirmPasswordToggle =
            document.getElementById(
                "confirmPasswordToggle"
            );

        const passwordIcon =
            document.getElementById("passwordIcon");

        const confirmPasswordIcon =
            document.getElementById(
                "confirmPasswordIcon"
            );

        const addStudentForm =
            document.getElementById("addStudentForm");

        const passwordMessage =
            document.getElementById("passwordMessage");

        passwordToggle.addEventListener(
            "click",
            function () {

                const hidden =
                    passwordInput.type === "password";

                passwordInput.type =
                    hidden ? "text" : "password";

                passwordIcon.classList.toggle(
                    "fa-eye",
                    !hidden
                );

                passwordIcon.classList.toggle(
                    "fa-eye-slash",
                    hidden
                );
            }
        );

        confirmPasswordToggle.addEventListener(
            "click",
            function () {

                const hidden =
                    confirmPasswordInput.type === "password";

                confirmPasswordInput.type =
                    hidden ? "text" : "password";

                confirmPasswordIcon.classList.toggle(
                    "fa-eye",
                    !hidden
                );

                confirmPasswordIcon.classList.toggle(
                    "fa-eye-slash",
                    hidden
                );
            }
        );

        addStudentForm.addEventListener(
            "submit",
            function (event) {

                if (
                    passwordInput.value !==
                    confirmPasswordInput.value
                ) {
                    event.preventDefault();

                    passwordMessage.textContent =
                        "Passwords do not match.";

                    passwordMessage.className =
                        "add-password-message password-error";

                    confirmPasswordInput.focus();
                }
            }
        );

    </script>

</body>

</html>