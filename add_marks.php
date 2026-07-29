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

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$adminId   = $_SESSION["admin_id"] ?? "Admin";

$successMessage = "";
$errorMessage   = "";

$studentId    = "";
$subjectName  = "";
$internalMarks = "";
$maximumMarks  = "";

/* ==================================================
   SAVE MARKS
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $studentId     = trim($_POST["student_id"] ?? "");
    $subjectName   = trim($_POST["subject_name"] ?? "");
    $internalMarks = trim($_POST["internal_marks"] ?? "");
    $maximumMarks  = trim($_POST["maximum_marks"] ?? "");

    if (
        $studentId === "" ||
        $subjectName === "" ||
        $internalMarks === "" ||
        $maximumMarks === ""
    ) {
        $errorMessage = "Please fill in all fields.";
    } elseif (
        !is_numeric($internalMarks) ||
        !is_numeric($maximumMarks)
    ) {
        $errorMessage = "Marks must be valid numbers.";
    } elseif ((float) $maximumMarks <= 0) {
        $errorMessage = "Maximum marks must be greater than zero.";
    } elseif ((float) $internalMarks < 0) {
        $errorMessage = "Internal marks cannot be negative.";
    } elseif ((float) $internalMarks > (float) $maximumMarks) {
        $errorMessage =
            "Internal marks cannot be greater than maximum marks.";
    } else {

        /* Check whether the selected student exists */

        $studentCheck = mysqli_prepare(
            $conn,
            "
            SELECT student_id
            FROM students
            WHERE student_id = ?
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param(
            $studentCheck,
            "s",
            $studentId
        );

        mysqli_stmt_execute($studentCheck);

        $studentResult = mysqli_stmt_get_result($studentCheck);

        if (mysqli_num_rows($studentResult) === 0) {
            $errorMessage = "The selected student does not exist.";
        } else {

            /* Prevent duplicate subject entry for the same student */

            $duplicateCheck = mysqli_prepare(
                $conn,
                "
                SELECT id
                FROM marks
                WHERE student_id = ?
                  AND LOWER(TRIM(subject_name)) =
                      LOWER(TRIM(?))
                LIMIT 1
                "
            );

            mysqli_stmt_bind_param(
                $duplicateCheck,
                "ss",
                $studentId,
                $subjectName
            );

            mysqli_stmt_execute($duplicateCheck);

            $duplicateResult =
                mysqli_stmt_get_result($duplicateCheck);

            if (mysqli_num_rows($duplicateResult) > 0) {
                $errorMessage =
                    "Marks for this student and subject already exist.";
            } else {

                $insertStatement = mysqli_prepare(
                    $conn,
                    "
                    INSERT INTO marks (
                        student_id,
                        subject_name,
                        internal_marks,
                        maximum_marks
                    )
                    VALUES (?, ?, ?, ?)
                    "
                );

                $internalMarksNumber = (float) $internalMarks;
                $maximumMarksNumber  = (float) $maximumMarks;

                mysqli_stmt_bind_param(
                    $insertStatement,
                    "ssdd",
                    $studentId,
                    $subjectName,
                    $internalMarksNumber,
                    $maximumMarksNumber
                );

                if (mysqli_stmt_execute($insertStatement)) {

                    header(
                        "Location: manage_marks.php?status=added"
                    );
                    exit();

                } else {
                    $errorMessage =
                        "Unable to save marks. Please try again.";
                }

                mysqli_stmt_close($insertStatement);
            }

            mysqli_stmt_close($duplicateCheck);
        }

        mysqli_stmt_close($studentCheck);
    }
}

/* ==================================================
   FETCH STUDENTS FOR DROPDOWN
================================================== */

$studentsQuery = mysqli_query(
    $conn,
    "
    SELECT
        student_id,
        full_name,
        department,
        year
    FROM students
    ORDER BY full_name ASC
    "
);

if (!$studentsQuery) {
    die(
        "Unable to load students: " .
        mysqli_error($conn)
    );
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

    <title>Add Marks | SSMS</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --background: #050b18;
            --sidebar: #071120;
            --card: rgba(13, 27, 48, 0.8);
            --border: rgba(127, 211, 255, 0.13);
            --primary: #00d4ff;
            --text: #f5f9ff;
            --muted: #8192aa;
            --green: #30d98b;
            --red: #ff647c;
            --yellow: #ffc857;
            --purple: #a78bfa;
        }

        body {
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(
                    circle at top right,
                    rgba(0, 212, 255, 0.1),
                    transparent 28%
                ),
                radial-gradient(
                    circle at bottom left,
                    rgba(167, 139, 250, 0.08),
                    transparent 30%
                ),
                var(--background);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        input,
        select,
        button {
            font: inherit;
        }

        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;

            display: flex;
            flex-direction: column;

            width: 255px;
            height: 100vh;
            padding: 24px 18px;

            background: rgba(6, 16, 31, 0.96);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 0 7px 23px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo {
            display: grid;
            place-items: center;

            width: 45px;
            height: 45px;

            color: #04101c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #8af0ff
            );
            border-radius: 13px;
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.2);
        }

        .sidebar-brand h2 {
            font-size: 19px;
            letter-spacing: 1px;
        }

        .sidebar-brand span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .sidebar-profile {
            display: flex;
            align-items: center;
            gap: 11px;

            margin: 22px 0;
            padding: 14px;

            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 14px;
        }

        .profile-avatar {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 39px;
            height: 39px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.09);
            border-radius: 11px;
        }

        .sidebar-profile h3 {
            max-width: 145px;
            overflow: hidden;
            font-size: 13px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-profile p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
        }

        .sidebar-menu {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-menu a,
        .sidebar-bottom a {
            display: flex;
            align-items: center;
            gap: 13px;

            padding: 12px 14px;

            color: #97a6ba;
            border: 1px solid transparent;
            border-radius: 11px;

            font-size: 12px;
            transition: 0.25s;
        }

        .sidebar-menu a i,
        .sidebar-bottom a i {
            width: 18px;
            text-align: center;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active,
        .sidebar-bottom a:hover {
            color: var(--text);
            background: rgba(0, 212, 255, 0.08);
            border-color: rgba(0, 212, 255, 0.12);
        }

        .sidebar-menu a.active {
            color: var(--primary);
        }

        .sidebar-bottom {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .sidebar-bottom .logout-link:hover {
            color: var(--red);
            background: rgba(255, 100, 124, 0.07);
            border-color: rgba(255, 100, 124, 0.12);
        }

        .admin-main {
            min-height: 100vh;
            margin-left: 255px;
            padding: 32px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .header-label {
            margin-bottom: 7px;
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .page-header h1 {
            margin-bottom: 7px;
            font-size: 26px;
        }

        .page-header span {
            color: var(--muted);
            font-size: 12px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 11px 16px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border: 1px solid rgba(0, 212, 255, 0.14);
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
            transition: 0.25s;
        }

        .back-button:hover {
            background: rgba(0, 212, 255, 0.13);
            transform: translateY(-1px);
        }

        .form-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(270px, 0.55fr);
            gap: 20px;
            align-items: start;
        }

        .form-card,
        .info-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .card-header {
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            margin-bottom: 6px;
            font-size: 16px;
        }

        .card-header p {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .marks-form {
            padding: 23px;
        }

        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-bottom: 20px;
            padding: 13px 14px;

            border-radius: 11px;

            font-size: 11px;
            line-height: 1.5;
        }

        .error-message {
            color: #ff9bac;
            background: rgba(255, 100, 124, 0.08);
            border: 1px solid rgba(255, 100, 124, 0.16);
        }

        .success-message {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
            border: 1px solid rgba(48, 217, 139, 0.16);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #c7d3e2;
            font-size: 10px;
            font-weight: 700;
        }

        .required {
            color: var(--red);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 2;

            color: var(--muted);
            font-size: 12px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            padding: 13px 14px 13px 41px;

            color: var(--text);
            background: rgba(255, 255, 255, 0.026);
            border: 1px solid var(--border);
            border-radius: 11px;
            outline: none;

            font-size: 11px;
            transition: 0.25s;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            border-color: rgba(0, 212, 255, 0.45);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.06);
        }

        .input-wrapper input::placeholder {
            color: #607086;
        }

        .input-wrapper select {
            appearance: none;
            cursor: pointer;
        }

        .input-wrapper select option {
            color: var(--text);
            background: #0d1b30;
        }

        .select-arrow {
            position: absolute;
            top: 50%;
            right: 14px;

            color: var(--muted);
            font-size: 10px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .field-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.5;
        }

        .live-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            grid-column: 1 / -1;

            margin-top: 2px;
            padding: 14px 16px;

            background: rgba(0, 212, 255, 0.035);
            border: 1px solid rgba(0, 212, 255, 0.11);
            border-radius: 12px;
        }

        .preview-label {
            color: var(--muted);
            font-size: 10px;
        }

        .preview-result {
            font-size: 14px;
            font-weight: 700;
        }

        .preview-result span {
            color: var(--primary);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;

            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .cancel-button,
        .save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 12px 18px;
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s;
        }

        .cancel-button {
            color: var(--muted);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
        }

        .save-button {
            color: #03111c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #78efff
            );
            border: none;
            box-shadow: 0 9px 25px rgba(0, 212, 255, 0.16);
        }

        .cancel-button:hover,
        .save-button:hover {
            transform: translateY(-1px);
        }

        .info-card {
            overflow: hidden;
        }

        .info-body {
            padding: 20px;
        }

        .info-item {
            display: flex;
            gap: 12px;

            padding: 14px 0;
            border-bottom: 1px solid rgba(127, 211, 255, 0.08);
        }

        .info-item:first-child {
            padding-top: 0;
        }

        .info-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-icon {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 36px;
            height: 36px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border-radius: 10px;
        }

        .info-item:nth-child(2) .info-icon {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.07);
        }

        .info-item:nth-child(3) .info-icon {
            color: var(--green);
            background: rgba(48, 217, 139, 0.07);
        }

        .info-item h3 {
            margin-bottom: 5px;
            font-size: 11px;
        }

        .info-item p {
            color: var(--muted);
            font-size: 9px;
            line-height: 1.6;
        }

        @media (max-width: 1000px) {

            .form-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 850px) {

            .admin-sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }

            .sidebar-bottom {
                flex-direction: row;
            }

            .admin-main {
                margin-left: 0;
                padding: 22px;
            }
        }

        @media (max-width: 650px) {

            .page-header {
                align-items: stretch;
                flex-direction: column;
            }

            .back-button {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width,
            .live-preview {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .cancel-button,
            .save-button {
                width: 100%;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

    <aside class="admin-sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>

            <div>
                <h2>SSMS</h2>
                <span>Admin Portal</span>
            </div>

        </div>

        <div class="sidebar-profile">

            <div class="profile-avatar">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>

                <h3>
                    <?php echo htmlspecialchars($adminName); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($adminId); ?>
                </p>

            </div>

        </div>

        <nav class="sidebar-menu">

            <a href="admin_dashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage_students.php">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Manage Students</span>
            </a>

            <a href="manage_attendance.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Attendance</span>
            </a>

            <a
                href="manage_marks.php"
                class="active"
            >
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

        <div class="sidebar-bottom">

            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                <span>Home Page</span>
            </a>

            <a
                href="logout.php"
                class="logout-link"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </div>

    </aside>

    <main class="admin-main">

        <header class="page-header">

            <div>

                <p class="header-label">
                    Internal Marks Management
                </p>

                <h1>Add Student Marks</h1>

                <span>
                    Enter and save a student's internal assessment marks.
                </span>

            </div>

            <a
                href="manage_marks.php"
                class="back-button"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Manage Marks
            </a>

        </header>

        <section class="form-layout">

            <article class="form-card">

                <div class="card-header">

                    <h2>Marks Information</h2>

                    <p>
                        Select a student and enter the subject marks carefully.
                    </p>

                </div>

                <form
                    action="add_marks.php"
                    method="POST"
                    class="marks-form"
                    id="marksForm"
                >

                    <?php if ($errorMessage !== ""): ?>

                        <div class="message error-message">

                            <i class="fa-solid fa-circle-exclamation"></i>

                            <span>
                                <?php
                                echo htmlspecialchars($errorMessage);
                                ?>
                            </span>

                        </div>

                    <?php endif; ?>

                    <?php if ($successMessage !== ""): ?>

                        <div class="message success-message">

                            <i class="fa-solid fa-circle-check"></i>

                            <span>
                                <?php
                                echo htmlspecialchars($successMessage);
                                ?>
                            </span>

                        </div>

                    <?php endif; ?>

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label for="student_id">
                                Select Student
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-user-graduate"></i>

                                <select
                                    name="student_id"
                                    id="student_id"
                                    required
                                >

                                    <option value="">
                                        Choose a student
                                    </option>

                                    <?php
                                    while (
                                        $student =
                                        mysqli_fetch_assoc($studentsQuery)
                                    ):
                                    ?>

                                        <option
                                            value="<?php
                                            echo htmlspecialchars(
                                                $student["student_id"]
                                            );
                                            ?>"
                                            <?php
                                            echo (
                                                $studentId ===
                                                $student["student_id"]
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $student["student_id"] .
                                                " — " .
                                                $student["full_name"] .
                                                " — " .
                                                $student["department"] .
                                                " — " .
                                                $student["year"]
                                            );
                                            ?>
                                        </option>

                                    <?php endwhile; ?>

                                </select>

                                <i
                                    class="fa-solid fa-chevron-down select-arrow"
                                ></i>

                            </div>

                            <small class="field-help">
                                Student ID, name, department and year are shown.
                            </small>

                        </div>

                        <div class="form-group full-width">

                            <label for="subject_name">
                                Subject Name
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-book-open"></i>

                                <input
                                    type="text"
                                    name="subject_name"
                                    id="subject_name"
                                    value="<?php
                                    echo htmlspecialchars($subjectName);
                                    ?>"
                                    placeholder="Example: Python"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <small class="field-help">
                                The same subject cannot be added twice for one student.
                            </small>

                        </div>

                        <div class="form-group">

                            <label for="internal_marks">
                                Internal Marks
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-pen-to-square"></i>

                                <input
                                    type="number"
                                    name="internal_marks"
                                    id="internal_marks"
                                    value="<?php
                                    echo htmlspecialchars($internalMarks);
                                    ?>"
                                    placeholder="Example: 28"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>

                        <div class="form-group">

                            <label for="maximum_marks">
                                Maximum Marks
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-bullseye"></i>

                                <input
                                    type="number"
                                    name="maximum_marks"
                                    id="maximum_marks"
                                    value="<?php
                                    echo htmlspecialchars($maximumMarks);
                                    ?>"
                                    placeholder="Example: 30"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>

                        <div class="live-preview">

                            <span class="preview-label">
                                Calculated percentage
                            </span>

                            <strong class="preview-result">
                                <span id="percentagePreview">0.00</span>%
                            </strong>

                        </div>

                    </div>

                    <div class="form-actions">

                        <a
                            href="manage_marks.php"
                            class="cancel-button"
                        >
                            <i class="fa-solid fa-xmark"></i>
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="save-button"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Marks
                        </button>

                    </div>

                </form>

            </article>

            <aside class="info-card">

                <div class="card-header">

                    <h2>Important Notes</h2>

                    <p>
                        Follow these rules while adding marks.
                    </p>

                </div>

                <div class="info-body">

                    <div class="info-item">

                        <div class="info-icon">
                            <i class="fa-solid fa-user-check"></i>
                        </div>

                        <div>

                            <h3>Select Correct Student</h3>

                            <p>
                                Check the student ID and name before saving.
                            </p>

                        </div>

                    </div>

                    <div class="info-item">

                        <div class="info-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <div>

                            <h3>Marks Validation</h3>

                            <p>
                                Internal marks cannot exceed maximum marks.
                            </p>

                        </div>

                    </div>

                    <div class="info-item">

                        <div class="info-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>

                        <div>

                            <h3>Duplicate Protection</h3>

                            <p>
                                A subject can be entered only once for each student.
                            </p>

                        </div>

                    </div>

                </div>

            </aside>

        </section>

    </main>

    <script>

        const internalMarksInput =
            document.getElementById("internal_marks");

        const maximumMarksInput =
            document.getElementById("maximum_marks");

        const percentagePreview =
            document.getElementById("percentagePreview");

        function updatePercentagePreview() {

            const internalMarks =
                parseFloat(internalMarksInput.value);

            const maximumMarks =
                parseFloat(maximumMarksInput.value);

            if (
                Number.isFinite(internalMarks) &&
                Number.isFinite(maximumMarks) &&
                maximumMarks > 0
            ) {
                const percentage =
                    (internalMarks / maximumMarks) * 100;

                percentagePreview.textContent =
                    percentage.toFixed(2);
            } else {
                percentagePreview.textContent = "0.00";
            }
        }

        internalMarksInput.addEventListener(
            "input",
            updatePercentagePreview
        );

        maximumMarksInput.addEventListener(
            "input",
            updatePercentagePreview
        );

        updatePercentagePreview();

        document
            .getElementById("marksForm")
            .addEventListener("submit", function (event) {

                const internalMarks =
                    parseFloat(internalMarksInput.value);

                const maximumMarks =
                    parseFloat(maximumMarksInput.value);

                if (
                    Number.isFinite(internalMarks) &&
                    Number.isFinite(maximumMarks) &&
                    internalMarks > maximumMarks
                ) {
                    event.preventDefault();

                    alert(
                        "Internal marks cannot be greater than maximum marks."
                    );
                }
            });

    </script>

</body>
</html>

<?php

mysqli_close($conn);

?>