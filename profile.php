<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: student_login.html");
    exit();
}

$student_id = $_SESSION["student_id"];
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $department = trim($_POST["department"]);
    $year = trim($_POST["year"]);

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($department) ||
        empty($year)
    ) {
        $message = "Please fill in all fields.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {

        $duplicate_sql = "SELECT id FROM students
                          WHERE email = ?
                          AND student_id != ?
                          LIMIT 1";

        $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);

        mysqli_stmt_bind_param(
            $duplicate_stmt,
            "ss",
            $email,
            $student_id
        );

        mysqli_stmt_execute($duplicate_stmt);

        $duplicate_result =
            mysqli_stmt_get_result($duplicate_stmt);

        if (mysqli_num_rows($duplicate_result) > 0) {

            $message = "This email is already used by another student.";
            $message_type = "error";

        } else {

            $update_sql = "UPDATE students
                           SET full_name = ?,
                               email = ?,
                               phone = ?,
                               department = ?,
                               year = ?
                           WHERE student_id = ?";

            $update_stmt =
                mysqli_prepare($conn, $update_sql);

            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssss",
                $full_name,
                $email,
                $phone,
                $department,
                $year,
                $student_id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                $_SESSION["student_name"] = $full_name;

                $message = "Profile updated successfully.";
                $message_type = "success";

            } else {

                $message = "Unable to update profile.";
                $message_type = "error";
            }
        }
    }
}

$student_sql = "SELECT student_id,
                       full_name,
                       email,
                       phone,
                       department,
                       year
                FROM students
                WHERE student_id = ?
                LIMIT 1";

$student_stmt = mysqli_prepare($conn, $student_sql);

mysqli_stmt_bind_param(
    $student_stmt,
    "s",
    $student_id
);

mysqli_stmt_execute($student_stmt);

$student_result =
    mysqli_stmt_get_result($student_stmt);

if (mysqli_num_rows($student_result) !== 1) {
    session_destroy();
    header("Location: student_login.html");
    exit();
}

$student = mysqli_fetch_assoc($student_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="student-profile-page">

<div class="student-dashboard-layout">

    <aside class="student-sidebar">

        <div class="student-sidebar-logo">

            <i class="fa-solid fa-graduation-cap"></i>

            <div>
                <h2>SSMS</h2>
                <p>Student Portal</p>
            </div>

        </div>

        <nav class="student-sidebar-menu">

            <a href="student_dashboard.php">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="profile.php" class="active">
                <i class="fa-solid fa-user"></i>
                My Profile
            </a>

            <a href="room_allocation.php">
                <i class="fa-solid fa-building"></i>
                Room Details
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-column"></i>
                Reports
            </a>

            <a href="logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>

    <main class="student-profile-main">

        <header class="student-profile-header">

            <div>
                <p class="dashboard-label">Student Portal</p>

                <h1>My Profile</h1>

                <p>
                    View and update your personal information.
                </p>
            </div>

            <a href="student_dashboard.php"
               class="profile-back-button">

                <i class="fa-solid fa-arrow-left"></i>
                Dashboard

            </a>

        </header>

        <?php if (!empty($message)): ?>

            <div class="profile-message <?php echo $message_type; ?>">

                <?php if ($message_type === "success"): ?>

                    <i class="fa-solid fa-circle-check"></i>

                <?php else: ?>

                    <i class="fa-solid fa-circle-exclamation"></i>

                <?php endif; ?>

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>

        <section class="profile-page-grid">

            <div class="profile-identity-card">

                <div class="profile-large-avatar">

                    <?php
                    echo strtoupper(
                        substr($student["full_name"], 0, 1)
                    );
                    ?>

                </div>

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $student["full_name"]
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars(
                        $student["student_id"]
                    );
                    ?>
                </p>

                <span class="profile-active-status">
                    <i class="fa-solid fa-circle-check"></i>
                    Active Student
                </span>

                <div class="profile-identity-details">

                    <div>
                        <i class="fa-solid fa-envelope"></i>

                        <span>
                            <?php
                            echo htmlspecialchars(
                                $student["email"]
                            );
                            ?>
                        </span>
                    </div>

                    <div>
                        <i class="fa-solid fa-phone"></i>

                        <span>
                            <?php
                            echo htmlspecialchars(
                                $student["phone"]
                            );
                            ?>
                        </span>
                    </div>

                    <div>
                        <i class="fa-solid fa-building-columns"></i>

                        <span>
                            <?php
                            echo htmlspecialchars(
                                $student["department"]
                            );
                            ?>
                        </span>
                    </div>

                </div>

            </div>

            <div class="profile-edit-card">

                <div class="profile-section-heading">

                    <div>
                        <p>Personal Details</p>
                        <h2>Edit Profile</h2>
                    </div>

                    <i class="fa-solid fa-user-pen"></i>

                </div>

                <form method="POST"
                      action="profile.php"
                      class="profile-edit-form">

                    <div class="profile-form-grid">

                        <div class="profile-form-field">

                            <label for="student_id">
                                Student ID
                            </label>

                            <input
                                type="text"
                                id="student_id"
                                value="<?php
                                echo htmlspecialchars(
                                    $student["student_id"]
                                );
                                ?>"
                                readonly>

                            <small>
                                Student ID cannot be changed.
                            </small>

                        </div>

                        <div class="profile-form-field">

                            <label for="full_name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?php
                                echo htmlspecialchars(
                                    $student["full_name"]
                                );
                                ?>"
                                required>

                        </div>

                        <div class="profile-form-field">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php
                                echo htmlspecialchars(
                                    $student["email"]
                                );
                                ?>"
                                required>

                        </div>

                        <div class="profile-form-field">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?php
                                echo htmlspecialchars(
                                    $student["phone"]
                                );
                                ?>"
                                required>

                        </div>

                        <div class="profile-form-field">

                            <label for="department">
                                Department
                            </label>

                            <select
                                id="department"
                                name="department"
                                required>

                                <option value="BCA"
                                    <?php
                                    if ($student["department"] === "BCA") {
                                        echo "selected";
                                    }
                                    ?>>
                                    BCA
                                </option>

                                <option value="BCA AI and ML"
                                    <?php
                                    if (
                                        $student["department"]
                                        === "BCA AI and ML"
                                    ) {
                                        echo "selected";
                                    }
                                    ?>>
                                    BCA AI and ML
                                </option>

                                <option value="BSc"
                                    <?php
                                    if ($student["department"] === "BSc") {
                                        echo "selected";
                                    }
                                    ?>>
                                    BSc
                                </option>

                                <option value="BCom"
                                    <?php
                                    if ($student["department"] === "BCom") {
                                        echo "selected";
                                    }
                                    ?>>
                                    BCom
                                </option>

                                <option value="BA"
                                    <?php
                                    if ($student["department"] === "BA") {
                                        echo "selected";
                                    }
                                    ?>>
                                    BA
                                </option>

                            </select>

                        </div>

                        <div class="profile-form-field">

                            <label for="year">
                                Academic Year
                            </label>

                            <select
                                id="year"
                                name="year"
                                required>

                                <option value="1st Year"
                                    <?php
                                    if ($student["year"] === "1st Year") {
                                        echo "selected";
                                    }
                                    ?>>
                                    1st Year
                                </option>

                                <option value="2nd Year"
                                    <?php
                                    if ($student["year"] === "2nd Year") {
                                        echo "selected";
                                    }
                                    ?>>
                                    2nd Year
                                </option>

                                <option value="3rd Year"
                                    <?php
                                    if ($student["year"] === "3rd Year") {
                                        echo "selected";
                                    }
                                    ?>>
                                    3rd Year
                                </option>

                            </select>

                        </div>

                    </div>

                    <button type="submit"
                            class="profile-update-button">

                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Changes

                    </button>

                </form>

            </div>

        </section>

    </main>

</div>

</body>
</html>