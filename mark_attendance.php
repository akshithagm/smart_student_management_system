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

$message = "";
$messageType = "";

$selectedStudentId = trim(
    $_GET["student_id"] ?? $_POST["student_id"] ?? ""
);

$attendanceDate = $_POST["attendance_date"] ?? date("Y-m-d");
$status = $_POST["status"] ?? "";

/* ==================================================
   FETCH ALL STUDENTS
================================================== */

$studentsSql = "
    SELECT
        student_id,
        full_name,
        department,
        year
    FROM students
    ORDER BY full_name ASC
";

$studentsResult = mysqli_query($conn, $studentsSql);

if (!$studentsResult) {
    die("Unable to load students: " . mysqli_error($conn));
}

/* ==================================================
   MARK ATTENDANCE
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selectedStudentId = trim($_POST["student_id"] ?? "");
    $attendanceDate = trim($_POST["attendance_date"] ?? "");
    $status = trim($_POST["status"] ?? "");

    if (
        $selectedStudentId === "" ||
        $attendanceDate === "" ||
        $status === ""
    ) {
        $message = "Please complete all attendance fields.";
        $messageType = "error";
    } elseif (
        $status !== "Present" &&
        $status !== "Absent"
    ) {
        $message = "Please select a valid attendance status.";
        $messageType = "error";
    } elseif ($attendanceDate > date("Y-m-d")) {
        $message = "Attendance cannot be marked for a future date.";
        $messageType = "error";
    } else {

        /* Verify student */

        $studentCheckSql = "
            SELECT student_id
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ";

        $studentCheckStmt = mysqli_prepare(
            $conn,
            $studentCheckSql
        );

        if (!$studentCheckStmt) {
            $message = "Unable to verify the selected student.";
            $messageType = "error";
        } else {

            mysqli_stmt_bind_param(
                $studentCheckStmt,
                "s",
                $selectedStudentId
            );

            mysqli_stmt_execute($studentCheckStmt);

            $studentCheckResult =
                mysqli_stmt_get_result($studentCheckStmt);

            if (mysqli_num_rows($studentCheckResult) !== 1) {

                $message = "The selected student was not found.";
                $messageType = "error";

            } else {

                /* Check duplicate attendance */

                $duplicateSql = "
                    SELECT id
                    FROM attendance_records
                    WHERE student_id = ?
                      AND attendance_date = ?
                    LIMIT 1
                ";

                $duplicateStmt = mysqli_prepare(
                    $conn,
                    $duplicateSql
                );

                if (!$duplicateStmt) {

                    $message =
                        "Unable to check the attendance record.";

                    $messageType = "error";

                } else {

                    mysqli_stmt_bind_param(
                        $duplicateStmt,
                        "ss",
                        $selectedStudentId,
                        $attendanceDate
                    );

                    mysqli_stmt_execute($duplicateStmt);

                    $duplicateResult =
                        mysqli_stmt_get_result($duplicateStmt);

                    if (mysqli_num_rows($duplicateResult) > 0) {

                        $message =
                            "Attendance has already been marked for this student on the selected date.";

                        $messageType = "error";

                    } else {

                        mysqli_begin_transaction($conn);

                        try {

                            /* Insert daily record */

                            $insertSql = "
                                INSERT INTO attendance_records
                                (
                                    student_id,
                                    attendance_date,
                                    status
                                )
                                VALUES (?, ?, ?)
                            ";

                            $insertStmt = mysqli_prepare(
                                $conn,
                                $insertSql
                            );

                            if (!$insertStmt) {
                                throw new Exception(
                                    "Unable to prepare attendance insertion."
                                );
                            }

                            mysqli_stmt_bind_param(
                                $insertStmt,
                                "sss",
                                $selectedStudentId,
                                $attendanceDate,
                                $status
                            );

                            if (!mysqli_stmt_execute($insertStmt)) {
                                throw new Exception(
                                    "Attendance record could not be saved."
                                );
                            }

                            mysqli_stmt_close($insertStmt);

                            /* Calculate updated attendance summary */

                            $summarySql = "
                                SELECT
                                    COUNT(*) AS total_classes,
                                    SUM(
                                        CASE
                                            WHEN status = 'Present'
                                            THEN 1
                                            ELSE 0
                                        END
                                    ) AS attended_classes
                                FROM attendance_records
                                WHERE student_id = ?
                            ";

                            $summaryStmt = mysqli_prepare(
                                $conn,
                                $summarySql
                            );

                            if (!$summaryStmt) {
                                throw new Exception(
                                    "Unable to calculate attendance summary."
                                );
                            }

                            mysqli_stmt_bind_param(
                                $summaryStmt,
                                "s",
                                $selectedStudentId
                            );

                            mysqli_stmt_execute($summaryStmt);

                            $summaryResult =
                                mysqli_stmt_get_result($summaryStmt);

                            $summary =
                                mysqli_fetch_assoc($summaryResult);

                            mysqli_stmt_close($summaryStmt);

                            $totalClasses =
                                (int) ($summary["total_classes"] ?? 0);

                            $attendedClasses =
                                (int) ($summary["attended_classes"] ?? 0);

                            $attendancePercentage =
                                $totalClasses > 0
                                    ? round(
                                        (
                                            $attendedClasses /
                                            $totalClasses
                                        ) * 100,
                                        2
                                    )
                                    : 0;

                            /* Check summary record */

                            $summaryCheckSql = "
                                SELECT id
                                FROM attendance
                                WHERE student_id = ?
                                LIMIT 1
                            ";

                            $summaryCheckStmt = mysqli_prepare(
                                $conn,
                                $summaryCheckSql
                            );

                            if (!$summaryCheckStmt) {
                                throw new Exception(
                                    "Unable to check attendance summary."
                                );
                            }

                            mysqli_stmt_bind_param(
                                $summaryCheckStmt,
                                "s",
                                $selectedStudentId
                            );

                            mysqli_stmt_execute(
                                $summaryCheckStmt
                            );

                            $summaryCheckResult =
                                mysqli_stmt_get_result(
                                    $summaryCheckStmt
                                );

                            $summaryExists =
                                mysqli_num_rows(
                                    $summaryCheckResult
                                ) > 0;

                            mysqli_stmt_close(
                                $summaryCheckStmt
                            );

                            if ($summaryExists) {

                                $updateSummarySql = "
                                    UPDATE attendance
                                    SET
                                        total_classes = ?,
                                        attended_classes = ?,
                                        attendance_percentage = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                    WHERE student_id = ?
                                ";

                                $summarySaveStmt =
                                    mysqli_prepare(
                                        $conn,
                                        $updateSummarySql
                                    );

                                if (!$summarySaveStmt) {
                                    throw new Exception(
                                        "Unable to prepare summary update."
                                    );
                                }

                                mysqli_stmt_bind_param(
                                    $summarySaveStmt,
                                    "iids",
                                    $totalClasses,
                                    $attendedClasses,
                                    $attendancePercentage,
                                    $selectedStudentId
                                );

                            } else {

                                $insertSummarySql = "
                                    INSERT INTO attendance
                                    (
                                        student_id,
                                        total_classes,
                                        attended_classes,
                                        attendance_percentage
                                    )
                                    VALUES (?, ?, ?, ?)
                                ";

                                $summarySaveStmt =
                                    mysqli_prepare(
                                        $conn,
                                        $insertSummarySql
                                    );

                                if (!$summarySaveStmt) {
                                    throw new Exception(
                                        "Unable to prepare summary insertion."
                                    );
                                }

                                mysqli_stmt_bind_param(
                                    $summarySaveStmt,
                                    "siid",
                                    $selectedStudentId,
                                    $totalClasses,
                                    $attendedClasses,
                                    $attendancePercentage
                                );
                            }

                            if (
                                !mysqli_stmt_execute(
                                    $summarySaveStmt
                                )
                            ) {
                                throw new Exception(
                                    "Attendance summary could not be updated."
                                );
                            }

                            mysqli_stmt_close(
                                $summarySaveStmt
                            );

                            mysqli_commit($conn);

                            echo "
                                <script>
                                    alert('Attendance marked successfully.');
                                    window.location.href =
                                        'manage_attendance.php';
                                </script>
                            ";

                            exit();

                        } catch (Exception $error) {

                            mysqli_rollback($conn);

                            $message =
                                "Attendance could not be saved. " .
                                $error->getMessage();

                            $messageType = "error";
                        }
                    }

                    mysqli_stmt_close($duplicateStmt);
                }
            }

            mysqli_stmt_close($studentCheckStmt);
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

    <title>Mark Attendance | SSMS</title>

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
        }

        body {
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(
                    circle at top right,
                    rgba(0, 212, 255, 0.1),
                    transparent 30%
                ),
                var(--background);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;

            display: flex;
            flex-direction: column;

            width: 255px;
            height: 100vh;
            padding: 24px 18px;

            background: rgba(6, 16, 31, 0.97);
            border-right: 1px solid var(--border);
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
        }

        .sidebar-brand h2 {
            font-size: 19px;
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
            place-items: center;

            width: 39px;
            height: 39px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.09);
            border-radius: 11px;
        }

        .sidebar-profile h3 {
            font-size: 13px;
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
            padding-top: 15px;
            border-top: 1px solid var(--border);
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
            gap: 8px;

            padding: 11px 16px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border: 1px solid rgba(0, 212, 255, 0.13);
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
        }

        .attendance-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 20px;
        }

        .attendance-form-card,
        .attendance-info-card {
            padding: 25px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .form-heading {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-bottom: 23px;
            padding-bottom: 18px;

            border-bottom: 1px solid var(--border);
        }

        .heading-icon {
            display: grid;
            place-items: center;

            width: 44px;
            height: 44px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 12px;
        }

        .form-heading h2 {
            margin-bottom: 5px;
            font-size: 16px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 10px;
        }

        .message {
            display: flex;
            align-items: center;
            gap: 9px;

            margin-bottom: 18px;
            padding: 12px 14px;

            border-radius: 10px;
            font-size: 11px;
        }

        .message-error {
            color: var(--red);
            background: rgba(255, 100, 124, 0.07);
            border: 1px solid rgba(255, 100, 124, 0.14);
        }

        .form-group {
            margin-bottom: 19px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;

            color: #dce7f4;
            font-size: 11px;
            font-weight: 700;
        }

        .input-box {
            display: flex;
            align-items: center;
            gap: 11px;

            padding: 0 14px;

            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .input-box:focus-within {
            border-color: rgba(0, 212, 255, 0.45);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.05);
        }

        .input-box i {
            color: var(--primary);
            font-size: 12px;
        }

        .input-box select,
        .input-box input {
            width: 100%;
            padding: 13px 0;

            color: var(--text);
            background: transparent;
            border: none;
            outline: none;

            font-size: 11px;
        }

        .input-box select option {
            color: #ffffff;
            background: #0b1729;
        }

        .status-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 13px;
        }

        .status-option input {
            position: absolute;
            opacity: 0;
        }

        .status-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 15px;

            color: var(--muted);
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 11px;
            cursor: pointer;

            font-size: 11px;
        }

        .present-option input:checked + label {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
            border-color: rgba(48, 217, 139, 0.35);
        }

        .absent-option input:checked + label {
            color: var(--red);
            background: rgba(255, 100, 124, 0.08);
            border-color: rgba(255, 100, 124, 0.35);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;

            margin-top: 24px;
            padding-top: 19px;

            border-top: 1px solid var(--border);
        }

        .cancel-button,
        .submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 11px 17px;

            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }

        .cancel-button {
            color: var(--muted);
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid var(--border);
        }

        .submit-button {
            color: #03111c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #7defff
            );
            border: none;
            cursor: pointer;
        }

        .attendance-info-card {
            height: fit-content;
        }

        .info-icon {
            display: grid;
            place-items: center;

            width: 48px;
            height: 48px;
            margin-bottom: 17px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 13px;
        }

        .attendance-info-card h2 {
            margin-bottom: 10px;
            font-size: 16px;
        }

        .attendance-info-card > p {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 13px;

            margin-top: 22px;
        }

        .info-list div {
            display: flex;
            align-items: flex-start;
            gap: 9px;

            color: #aebcd0;
            font-size: 10px;
            line-height: 1.5;
        }

        .info-list i {
            margin-top: 2px;
            color: var(--green);
        }

        @media (max-width: 900px) {

            .admin-sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .admin-main {
                margin-left: 0;
                padding: 22px;
            }

            .attendance-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .page-header {
                align-items: stretch;
                flex-direction: column;
            }

            .status-options {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .cancel-button,
            .submit-button {
                width: 100%;
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

            <a
                href="manage_attendance.php"
                class="active"
            >
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

        <div class="sidebar-bottom">

            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                <span>Home Page</span>
            </a>

            <a href="logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </div>

    </aside>

    <main class="admin-main">

        <header class="page-header">

            <div>

                <p class="header-label">
                    Attendance Management
                </p>

                <h1>Mark Attendance</h1>

                <span>
                    Record a student's daily attendance.
                </span>

            </div>

            <a
                href="manage_attendance.php"
                class="back-button"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Attendance
            </a>

        </header>

        <section class="attendance-layout">

            <div class="attendance-form-card">

                <div class="form-heading">

                    <div class="heading-icon">
                        <i class="fa-solid fa-calendar-plus"></i>
                    </div>

                    <div>
                        <h2>Attendance Details</h2>
                        <p>
                            Select a student, date and attendance status.
                        </p>
                    </div>

                </div>

                <?php if ($message !== ""): ?>

                    <div class="message message-error">

                        <i class="fa-solid fa-circle-exclamation"></i>

                        <?php echo htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>

                <form
                    action="mark_attendance.php"
                    method="POST"
                >

                    <div class="form-group">

                        <label for="student_id">
                            Select Student
                        </label>

                        <div class="input-box">

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
                                mysqli_data_seek($studentsResult, 0);

                                while (
                                    $student =
                                        mysqli_fetch_assoc(
                                            $studentsResult
                                        )
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
                                            $selectedStudentId ===
                                            $student["student_id"]
                                        ) ? "selected" : "";
                                        ?>
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $student["student_id"] .
                                            " - " .
                                            $student["full_name"] .
                                            " (" .
                                            $student["department"] .
                                            ")"
                                        );
                                        ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="attendance_date">
                            Attendance Date
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-calendar-day"></i>

                            <input
                                type="date"
                                name="attendance_date"
                                id="attendance_date"
                                value="<?php
                                echo htmlspecialchars(
                                    $attendanceDate
                                );
                                ?>"
                                max="<?php echo date("Y-m-d"); ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label>Attendance Status</label>

                        <div class="status-options">

                            <div
                                class="status-option present-option"
                            >

                                <input
                                    type="radio"
                                    name="status"
                                    id="present"
                                    value="Present"
                                    <?php
                                    echo $status === "Present"
                                        ? "checked"
                                        : "";
                                    ?>
                                    required
                                >

                                <label for="present">

                                    <i class="fa-solid fa-user-check"></i>

                                    Present

                                </label>

                            </div>

                            <div
                                class="status-option absent-option"
                            >

                                <input
                                    type="radio"
                                    name="status"
                                    id="absent"
                                    value="Absent"
                                    <?php
                                    echo $status === "Absent"
                                        ? "checked"
                                        : "";
                                    ?>
                                    required
                                >

                                <label for="absent">

                                    <i class="fa-solid fa-user-xmark"></i>

                                    Absent

                                </label>

                            </div>

                        </div>

                    </div>

                    <div class="form-actions">

                        <a
                            href="manage_attendance.php"
                            class="cancel-button"
                        >
                            <i class="fa-solid fa-xmark"></i>
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="submit-button"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Attendance
                        </button>

                    </div>

                </form>

            </div>

            <aside class="attendance-info-card">

                <div class="info-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <h2>Attendance Information</h2>

                <p>
                    Daily records and the student's attendance percentage
                    will be updated automatically.
                </p>

                <div class="info-list">

                    <div>
                        <i class="fa-solid fa-check"></i>
                        <span>
                            One record is allowed per student per date.
                        </span>
                    </div>

                    <div>
                        <i class="fa-solid fa-check"></i>
                        <span>
                            Future attendance dates are not allowed.
                        </span>
                    </div>

                    <div>
                        <i class="fa-solid fa-check"></i>
                        <span>
                            Present increases attended classes.
                        </span>
                    </div>

                    <div>
                        <i class="fa-solid fa-check"></i>
                        <span>
                            Total classes and percentage update
                            automatically.
                        </span>
                    </div>

                </div>

            </aside>

        </section>

    </main>

</body>
</html>