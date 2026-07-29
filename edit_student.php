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
   GET STUDENT DATABASE ID
================================================== */

$studentDatabaseId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$studentDatabaseId) {
    header("Location: manage_students.php");
    exit();
}

/* ==================================================
   FETCH STUDENT
================================================== */

$studentSql = "
    SELECT
        id,
        student_id,
        full_name,
        email,
        phone,
        department,
        year
    FROM students
    WHERE id = ?
    LIMIT 1
";

$studentStmt = mysqli_prepare($conn, $studentSql);

if (!$studentStmt) {
    die("Unable to prepare student query: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $studentStmt,
    "i",
    $studentDatabaseId
);

mysqli_stmt_execute($studentStmt);

$studentResult = mysqli_stmt_get_result($studentStmt);

if (mysqli_num_rows($studentResult) !== 1) {
    mysqli_stmt_close($studentStmt);

    echo "
        <script>
            alert('Student record not found.');
            window.location.href = 'manage_students.php';
        </script>
    ";

    exit();
}

$student = mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($studentStmt);

/* ==================================================
   UPDATE STUDENT
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $phone = trim($_POST["phone"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $full_name === "" ||
        $email === "" ||
        $phone === "" ||
        $department === "" ||
        $year === ""
    ) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    }

    elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
        $message = "Phone number must contain exactly 10 digits.";
        $messageType = "error";
    }

    elseif (
        $new_password !== "" &&
        strlen($new_password) < 6
    ) {
        $message = "New password must contain at least 6 characters.";
        $messageType = "error";
    }

    elseif (
        $new_password !== "" &&
        $new_password !== $confirm_password
    ) {
        $message = "New password and confirm password do not match.";
        $messageType = "error";
    }

    else {

        /* Check whether another student uses the same email */

        $emailCheckSql = "
            SELECT id
            FROM students
            WHERE email = ?
              AND id != ?
            LIMIT 1
        ";

        $emailCheckStmt = mysqli_prepare(
            $conn,
            $emailCheckSql
        );

        if (!$emailCheckStmt) {
            $message = "Unable to validate the email address.";
            $messageType = "error";
        } else {

            mysqli_stmt_bind_param(
                $emailCheckStmt,
                "si",
                $email,
                $studentDatabaseId
            );

            mysqli_stmt_execute($emailCheckStmt);

            $emailCheckResult =
                mysqli_stmt_get_result($emailCheckStmt);

            if (mysqli_num_rows($emailCheckResult) > 0) {

                $message =
                    "This email address is already used by another student.";

                $messageType = "error";

            } else {

                /* Update with optional password */

                if ($new_password !== "") {

                    $hashedPassword = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $updateSql = "
                        UPDATE students
                        SET
                            full_name = ?,
                            email = ?,
                            phone = ?,
                            department = ?,
                            year = ?,
                            password = ?
                        WHERE id = ?
                    ";

                    $updateStmt = mysqli_prepare(
                        $conn,
                        $updateSql
                    );

                    if ($updateStmt) {

                        mysqli_stmt_bind_param(
                            $updateStmt,
                            "ssssssi",
                            $full_name,
                            $email,
                            $phone,
                            $department,
                            $year,
                            $hashedPassword,
                            $studentDatabaseId
                        );
                    }

                } else {

                    $updateSql = "
                        UPDATE students
                        SET
                            full_name = ?,
                            email = ?,
                            phone = ?,
                            department = ?,
                            year = ?
                        WHERE id = ?
                    ";

                    $updateStmt = mysqli_prepare(
                        $conn,
                        $updateSql
                    );

                    if ($updateStmt) {

                        mysqli_stmt_bind_param(
                            $updateStmt,
                            "sssssi",
                            $full_name,
                            $email,
                            $phone,
                            $department,
                            $year,
                            $studentDatabaseId
                        );
                    }
                }

                if (!$updateStmt) {

                    $message =
                        "Unable to prepare the update query.";

                    $messageType = "error";

                } elseif (mysqli_stmt_execute($updateStmt)) {

                    mysqli_stmt_close($updateStmt);
                    mysqli_stmt_close($emailCheckStmt);

                    echo "
                        <script>
                            alert('Student updated successfully.');
                            window.location.href =
                                'manage_students.php';
                        </script>
                    ";

                    exit();

                } else {

                    $message =
                        "Student could not be updated. Please try again.";

                    $messageType = "error";

                    mysqli_stmt_close($updateStmt);
                }
            }

            mysqli_stmt_close($emailCheckStmt);
        }
    }

    /* Keep entered values after validation error */

    $student["full_name"] = $full_name;
    $student["email"] = $email;
    $student["phone"] = $phone;
    $student["department"] = $department;
    $student["year"] = $year;
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

    <title>Edit Student | SSMS</title>

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
                    <?php echo htmlspecialchars($admin_name); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($admin_id); ?>
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

                <h1>Edit Student</h1>

                <span>
                    Update student personal and academic information.
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

                        <i class="fa-solid fa-user-pen"></i>

                    </div>

                    <div>

                        <p>Update Student</p>

                        <h2>
                            <?php
                            echo htmlspecialchars(
                                $student["full_name"]
                            );
                            ?>
                        </h2>

                        <span>
                            Student ID:
                            <?php
                            echo htmlspecialchars(
                                $student["student_id"]
                            );
                            ?>
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
                    action="edit_student.php?id=<?php
                    echo urlencode($studentDatabaseId);
                    ?>"
                    method="POST"
                    class="add-student-form"
                    id="editStudentForm"
                >

                    <div class="add-student-form-row">

                        <div class="add-student-form-group">

                            <label for="student_id">
                                Student ID
                            </label>

                            <div
                                class="add-student-input-box edit-readonly-input"
                            >

                                <i class="fa-solid fa-id-card"></i>

                                <input
                                    type="text"
                                    id="student_id"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $student["student_id"]
                                    );
                                    ?>"
                                    readonly
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
                                        $student["full_name"]
                                    );
                                    ?>"
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
                                        $student["email"]
                                    );
                                    ?>"
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
                                        $student["phone"]
                                    );
                                    ?>"
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
                                        $student["department"]
                                    );
                                    ?>"
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

                                    <?php
                                    $yearOptions = [
                                        "1st Year",
                                        "2nd Year",
                                        "3rd Year",
                                        "4th Year"
                                    ];

                                    foreach ($yearOptions as $option):
                                    ?>

                                        <option
                                            value="<?php
                                            echo htmlspecialchars($option);
                                            ?>"
                                            <?php
                                            echo (
                                                $student["year"] === $option
                                            ) ? "selected" : "";
                                            ?>
                                        >
                                            <?php
                                            echo htmlspecialchars($option);
                                            ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                    </div>

                    <div class="edit-password-section">

                        <div class="edit-password-heading">

                            <i class="fa-solid fa-key"></i>

                            <div>
                                <h3>Change Password</h3>

                                <p>
                                    Leave both fields blank to keep the
                                    current password.
                                </p>
                            </div>

                        </div>

                        <div class="add-student-form-row">

                            <div class="add-student-form-group">

                                <label for="new_password">
                                    New Password
                                </label>

                                <div class="add-student-input-box">

                                    <i class="fa-solid fa-lock"></i>

                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        placeholder="Enter new password"
                                        minlength="6"
                                    >

                                    <button
                                        type="button"
                                        class="add-password-toggle"
                                        id="passwordToggle"
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
                                    Confirm New Password
                                </label>

                                <div class="add-student-input-box">

                                    <i class="fa-solid fa-shield-halved"></i>

                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        placeholder="Confirm new password"
                                        minlength="6"
                                    >

                                    <button
                                        type="button"
                                        class="add-password-toggle"
                                        id="confirmPasswordToggle"
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

                    </div>

                    <div class="add-student-form-actions">

                        <a href="manage_students.php">

                            <i class="fa-solid fa-xmark"></i>

                            Cancel

                        </a>

                        <button type="submit">

                            <i class="fa-solid fa-floppy-disk"></i>

                            Save Changes

                        </button>

                    </div>

                </form>

            </div>

            <aside class="add-student-information-card">

                <div class="add-info-main-icon">

                    <i class="fa-solid fa-circle-info"></i>

                </div>

                <h2>Editing Information</h2>

                <p>
                    Update the student details carefully. The Student ID
                    is locked because attendance and marks use it.
                </p>

                <div class="add-info-list">

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Student ID cannot be modified.
                        </span>

                    </div>

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Email must remain unique.
                        </span>

                    </div>

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Leave password blank to keep it unchanged.
                        </span>

                    </div>

                    <div>

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Updates appear immediately in the student
                            profile.
                        </span>

                    </div>

                </div>

            </aside>

        </section>

    </main>

    <script>

        const passwordInput =
            document.getElementById("new_password");

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

        const editStudentForm =
            document.getElementById("editStudentForm");

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

        editStudentForm.addEventListener(
            "submit",
            function (event) {

                if (
                    passwordInput.value !== "" &&
                    passwordInput.value !==
                    confirmPasswordInput.value
                ) {
                    event.preventDefault();

                    passwordMessage.textContent =
                        "New passwords do not match.";

                    passwordMessage.className =
                        "add-password-message password-error";

                    confirmPasswordInput.focus();
                }
            }
        );

    </script>

</body>
</html>