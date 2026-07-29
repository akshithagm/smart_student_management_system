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

$studentId = trim($_GET["student_id"] ?? "");

if ($studentId === "") {
    header("Location: manage_marks.php");
    exit();
}

/* ==================================================
   FETCH STUDENT DETAILS
================================================== */

$studentStatement = mysqli_prepare(
    $conn,
    "
    SELECT
        student_id,
        full_name,
        email,
        department,
        year
    FROM students
    WHERE student_id = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $studentStatement,
    "s",
    $studentId
);

mysqli_stmt_execute($studentStatement);

$studentResult = mysqli_stmt_get_result($studentStatement);

if (mysqli_num_rows($studentResult) === 0) {
    mysqli_stmt_close($studentStatement);
    header("Location: manage_marks.php");
    exit();
}

$student = mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($studentStatement);

/* ==================================================
   FETCH ALL MARKS OF THE STUDENT
================================================== */

$marksStatement = mysqli_prepare(
    $conn,
    "
    SELECT
        id,
        subject_name,
        internal_marks,
        maximum_marks,
        created_at,
        updated_at
    FROM marks
    WHERE student_id = ?
    ORDER BY subject_name ASC
    "
);

mysqli_stmt_bind_param(
    $marksStatement,
    "s",
    $studentId
);

mysqli_stmt_execute($marksStatement);

$marksResult = mysqli_stmt_get_result($marksStatement);

$marksRecords = [];

$totalSubjects      = 0;
$totalMarksObtained = 0;
$totalMaximumMarks  = 0;
$highestPercentage  = 0;
$lowestPercentage   = 0;

while ($row = mysqli_fetch_assoc($marksResult)) {

    $internalMarks = (float) $row["internal_marks"];
    $maximumMarks  = (float) $row["maximum_marks"];

    $percentage = 0;

    if ($maximumMarks > 0) {
        $percentage =
            ($internalMarks / $maximumMarks) * 100;
    }

    $row["percentage"] = $percentage;

    $marksRecords[] = $row;

    $totalSubjects++;
    $totalMarksObtained += $internalMarks;
    $totalMaximumMarks  += $maximumMarks;

    if ($totalSubjects === 1) {
        $highestPercentage = $percentage;
        $lowestPercentage  = $percentage;
    } else {
        if ($percentage > $highestPercentage) {
            $highestPercentage = $percentage;
        }

        if ($percentage < $lowestPercentage) {
            $lowestPercentage = $percentage;
        }
    }
}

mysqli_stmt_close($marksStatement);

$overallPercentage = 0;

if ($totalMaximumMarks > 0) {
    $overallPercentage =
        ($totalMarksObtained / $totalMaximumMarks) * 100;
}

/* ==================================================
   PERFORMANCE HELPER
================================================== */

function getPerformanceLabel($percentage)
{
    if ($percentage >= 85) {
        return "Excellent";
    }

    if ($percentage >= 70) {
        return "Good";
    }

    if ($percentage >= 50) {
        return "Average";
    }

    return "Needs Improvement";
}

function getPerformanceClass($percentage)
{
    if ($percentage >= 85) {
        return "excellent";
    }

    if ($percentage >= 70) {
        return "good";
    }

    if ($percentage >= 50) {
        return "average";
    }

    return "needs-improvement";
}

$overallPerformance =
    getPerformanceLabel($overallPercentage);

$overallPerformanceClass =
    getPerformanceClass($overallPercentage);

/* ==================================================
   STUDENT INITIALS
================================================== */

$nameParts = preg_split(
    "/\s+/",
    trim($student["full_name"])
);

$studentInitials = "";

foreach (
    array_slice($nameParts, 0, 2)
    as $namePart
) {
    if ($namePart !== "") {
        $studentInitials .= strtoupper(
            substr($namePart, 0, 1)
        );
    }
}

if ($studentInitials === "") {
    $studentInitials = "ST";
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

    <title>
        Marks Report - <?php
        echo htmlspecialchars($student["full_name"]);
        ?>
    </title>

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
            --card: rgba(13, 27, 48, 0.82);
            --border: rgba(127, 211, 255, 0.13);
            --primary: #00d4ff;
            --text: #f5f9ff;
            --muted: #8192aa;
            --green: #30d98b;
            --red: #ff647c;
            --yellow: #ffc857;
            --blue: #62a8ff;
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

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .header-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 11px 16px;
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s;
        }

        .back-button {
            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border: 1px solid rgba(0, 212, 255, 0.14);
        }

        .print-button {
            color: #03111c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #78efff
            );
            border: none;
            box-shadow: 0 9px 25px rgba(0, 212, 255, 0.16);
        }

        .header-button:hover {
            transform: translateY(-1px);
        }

        .student-report-card {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 22px;
            align-items: center;

            margin-bottom: 22px;
            padding: 24px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            backdrop-filter: blur(18px);
        }

        .student-avatar {
            display: grid;
            place-items: center;

            width: 78px;
            height: 78px;

            color: var(--primary);
            background:
                linear-gradient(
                    135deg,
                    rgba(0, 212, 255, 0.13),
                    rgba(167, 139, 250, 0.09)
                );
            border: 1px solid rgba(0, 212, 255, 0.18);
            border-radius: 21px;

            font-size: 26px;
            font-weight: 700;
        }

        .student-info h2 {
            margin-bottom: 6px;
            font-size: 21px;
        }

        .student-info > p {
            margin-bottom: 15px;
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
        }

        .student-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            padding: 8px 10px;

            color: #b7c4d5;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 9px;

            font-size: 10px;
        }

        .meta-chip i {
            color: var(--primary);
        }

        .overall-score {
            min-width: 165px;
            text-align: right;
        }

        .overall-score small {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .overall-score strong {
            display: block;
            margin-bottom: 8px;
            font-size: 28px;
        }

        .performance-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            padding: 7px 10px;
            border-radius: 999px;

            font-size: 9px;
            font-weight: 700;
        }

        .performance-badge.excellent {
            color: var(--green);
            background: rgba(48, 217, 139, 0.09);
            border: 1px solid rgba(48, 217, 139, 0.16);
        }

        .performance-badge.good {
            color: var(--blue);
            background: rgba(98, 168, 255, 0.09);
            border: 1px solid rgba(98, 168, 255, 0.16);
        }

        .performance-badge.average {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.09);
            border: 1px solid rgba(255, 200, 87, 0.16);
        }

        .performance-badge.needs-improvement {
            color: var(--red);
            background: rgba(255, 100, 124, 0.09);
            border: 1px solid rgba(255, 100, 124, 0.16);
        }

        .statistics-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;

            padding: 19px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            backdrop-filter: blur(18px);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            top: -25px;
            right: -25px;

            width: 70px;
            height: 70px;

            background: rgba(0, 212, 255, 0.05);
            border-radius: 50%;
        }

        .stat-icon {
            display: grid;
            place-items: center;

            width: 37px;
            height: 37px;
            margin-bottom: 13px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 10px;
        }

        .stat-card:nth-child(2) .stat-icon {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
        }

        .stat-card:nth-child(3) .stat-icon {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.08);
        }

        .stat-card:nth-child(4) .stat-icon {
            color: var(--purple);
            background: rgba(167, 139, 250, 0.08);
        }

        .stat-card h3 {
            margin-bottom: 5px;
            font-size: 22px;
        }

        .stat-card p {
            color: var(--muted);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .report-card {
            overflow: hidden;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .report-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
        }

        .report-card-header h2 {
            margin-bottom: 5px;
            font-size: 16px;
        }

        .report-card-header p {
            color: var(--muted);
            font-size: 10px;
        }

        .record-count {
            padding: 7px 10px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border: 1px solid rgba(0, 212, 255, 0.13);
            border-radius: 9px;

            font-size: 9px;
            font-weight: 700;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 17px;
            border-bottom: 1px solid rgba(127, 211, 255, 0.08);
            text-align: left;
            vertical-align: middle;
        }

        th {
            color: #8293aa;
            background: rgba(255, 255, 255, 0.018);

            font-size: 8px;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            color: #dce6f3;
            font-size: 10px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: 0.2s;
        }

        tbody tr:hover {
            background: rgba(0, 212, 255, 0.025);
        }

        .subject-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .subject-icon {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 33px;
            height: 33px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border-radius: 9px;
        }

        .subject-cell strong {
            display: block;
            margin-bottom: 3px;
            font-size: 10px;
        }

        .subject-cell span {
            color: var(--muted);
            font-size: 8px;
        }

        .marks-value {
            font-weight: 700;
        }

        .percentage-cell {
            min-width: 170px;
        }

        .percentage-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 7px;
        }

        .percentage-top strong {
            color: var(--primary);
            font-size: 10px;
        }

        .percentage-top span {
            color: var(--muted);
            font-size: 8px;
        }

        .progress-track {
            width: 100%;
            height: 6px;

            overflow: hidden;

            background: rgba(255, 255, 255, 0.05);
            border-radius: 999px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(
                90deg,
                var(--primary),
                #7cefff
            );
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            padding: 8px 10px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.06);
            border: 1px solid rgba(0, 212, 255, 0.12);
            border-radius: 8px;

            font-size: 8px;
            font-weight: 700;
            transition: 0.2s;
        }

        .action-button:hover {
            background: rgba(0, 212, 255, 0.12);
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-state i {
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 36px;
        }

        .empty-state h3 {
            margin-bottom: 7px;
            font-size: 16px;
        }

        .empty-state p {
            margin-bottom: 18px;
            color: var(--muted);
            font-size: 10px;
        }

        .empty-state a {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            padding: 10px 14px;

            color: #03111c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #78efff
            );
            border-radius: 9px;

            font-size: 9px;
            font-weight: 700;
        }

        @media (max-width: 1050px) {

            .statistics-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .student-report-card {
                grid-template-columns: auto 1fr;
            }

            .overall-score {
                grid-column: 1 / -1;
                text-align: left;
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

            .header-actions {
                flex-direction: column;
            }

            .header-button {
                width: 100%;
            }

            .student-report-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .student-avatar {
                margin: auto;
            }

            .student-meta {
                justify-content: center;
            }

            .overall-score {
                text-align: center;
            }

            .statistics-grid {
                grid-template-columns: 1fr;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .report-card-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media print {

            body {
                color: #111;
                background: #fff;
            }

            .admin-sidebar,
            .header-actions,
            .action-column {
                display: none !important;
            }

            .admin-main {
                margin-left: 0;
                padding: 0;
            }

            .page-header {
                margin-bottom: 18px;
            }

            .page-header h1,
            .student-info h2,
            .stat-card h3,
            .report-card-header h2,
            td {
                color: #111;
            }

            .page-header span,
            .student-info > p,
            .student-meta,
            .stat-card p,
            .report-card-header p,
            th {
                color: #555;
            }

            .student-report-card,
            .stat-card,
            .report-card {
                background: #fff;
                border: 1px solid #d8d8d8;
                box-shadow: none;
            }

            .student-avatar {
                color: #111;
                background: #f3f3f3;
                border-color: #ddd;
            }

            .performance-badge {
                border: 1px solid #ccc;
            }

            .progress-track {
                background: #e8e8e8;
            }

            .progress-fill {
                background: #333;
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

                <h1>Student Marks Report</h1>

                <span>
                    Complete subject-wise internal assessment report.
                </span>

            </div>

            <div class="header-actions">

                <a
                    href="manage_marks.php"
                    class="header-button back-button"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back
                </a>

                <button
                    type="button"
                    class="header-button print-button"
                    onclick="window.print()"
                >
                    <i class="fa-solid fa-print"></i>
                    Print Report
                </button>

            </div>

        </header>

        <section class="student-report-card">

            <div class="student-avatar">
                <?php
                echo htmlspecialchars($studentInitials);
                ?>
            </div>

            <div class="student-info">

                <h2>
                    <?php
                    echo htmlspecialchars($student["full_name"]);
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars($student["student_id"]);
                    ?>
                </p>

                <div class="student-meta">

                    <div class="meta-chip">
                        <i class="fa-solid fa-building-columns"></i>
                        <?php
                        echo htmlspecialchars(
                            $student["department"]
                        );
                        ?>
                    </div>

                    <div class="meta-chip">
                        <i class="fa-solid fa-calendar-days"></i>
                        <?php
                        echo htmlspecialchars($student["year"]);
                        ?>
                    </div>

                    <div class="meta-chip">
                        <i class="fa-solid fa-envelope"></i>
                        <?php
                        echo htmlspecialchars($student["email"]);
                        ?>
                    </div>

                </div>

            </div>

            <div class="overall-score">

                <small>Overall Percentage</small>

                <strong>
                    <?php
                    echo number_format(
                        $overallPercentage,
                        2
                    );
                    ?>%
                </strong>

                <span
                    class="performance-badge <?php
                    echo $overallPerformanceClass;
                    ?>"
                >
                    <i class="fa-solid fa-award"></i>

                    <?php
                    echo htmlspecialchars(
                        $overallPerformance
                    );
                    ?>
                </span>

            </div>

        </section>

        <section class="statistics-grid">

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-book-open"></i>
                </div>

                <h3>
                    <?php echo $totalSubjects; ?>
                </h3>

                <p>Total Subjects</p>

            </article>

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-check-double"></i>
                </div>

                <h3>
                    <?php
                    echo number_format(
                        $totalMarksObtained,
                        2
                    );
                    ?>
                </h3>

                <p>Marks Obtained</p>

            </article>

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-bullseye"></i>
                </div>

                <h3>
                    <?php
                    echo number_format(
                        $totalMaximumMarks,
                        2
                    );
                    ?>
                </h3>

                <p>Maximum Marks</p>

            </article>

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>

                <h3>
                    <?php
                    echo number_format(
                        $highestPercentage,
                        2
                    );
                    ?>%
                </h3>

                <p>Highest Subject Score</p>

            </article>

        </section>

        <section class="report-card">

            <div class="report-card-header">

                <div>

                    <h2>Subject-wise Marks</h2>

                    <p>
                        Detailed marks, percentage and performance for every subject.
                    </p>

                </div>

                <span class="record-count">
                    <?php echo $totalSubjects; ?> Records
                </span>

            </div>

            <?php if ($totalSubjects > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>Subject</th>
                                <th>Internal Marks</th>
                                <th>Maximum Marks</th>
                                <th>Percentage</th>
                                <th>Performance</th>
                                <th class="action-column">Action</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($marksRecords as $record): ?>

                                <?php

                                $percentage =
                                    (float) $record["percentage"];

                                $performance =
                                    getPerformanceLabel(
                                        $percentage
                                    );

                                $performanceClass =
                                    getPerformanceClass(
                                        $percentage
                                    );

                                $progressWidth = min(
                                    100,
                                    max(0, $percentage)
                                );

                                ?>

                                <tr>

                                    <td>

                                        <div class="subject-cell">

                                            <div class="subject-icon">
                                                <i class="fa-solid fa-book"></i>
                                            </div>

                                            <div>

                                                <strong>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $record["subject_name"]
                                                    );
                                                    ?>
                                                </strong>

                                                <span>
                                                    Record #<?php
                                                    echo (int) $record["id"];
                                                    ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>

                                    <td class="marks-value">
                                        <?php
                                        echo number_format(
                                            (float)
                                            $record["internal_marks"],
                                            2
                                        );
                                        ?>
                                    </td>

                                    <td class="marks-value">
                                        <?php
                                        echo number_format(
                                            (float)
                                            $record["maximum_marks"],
                                            2
                                        );
                                        ?>
                                    </td>

                                    <td class="percentage-cell">

                                        <div class="percentage-top">

                                            <strong>
                                                <?php
                                                echo number_format(
                                                    $percentage,
                                                    2
                                                );
                                                ?>%
                                            </strong>

                                            <span>
                                                <?php
                                                echo number_format(
                                                    (float)
                                                    $record["internal_marks"],
                                                    2
                                                );
                                                ?>
                                                /
                                                <?php
                                                echo number_format(
                                                    (float)
                                                    $record["maximum_marks"],
                                                    2
                                                );
                                                ?>
                                            </span>

                                        </div>

                                        <div class="progress-track">

                                            <div
                                                class="progress-fill"
                                                style="width: <?php
                                                echo $progressWidth;
                                                ?>%;"
                                            ></div>

                                        </div>

                                    </td>

                                    <td>

                                        <span
                                            class="performance-badge <?php
                                            echo $performanceClass;
                                            ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $performance
                                            );
                                            ?>
                                        </span>

                                    </td>

                                    <td class="action-column">

                                        <a
                                            href="edit_marks.php?id=<?php
                                            echo (int) $record["id"];
                                            ?>"
                                            class="action-button"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                            Edit
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-folder-open"></i>

                    <h3>No Marks Available</h3>

                    <p>
                        No internal marks have been added for this student yet.
                    </p>

                    <a href="add_marks.php">
                        <i class="fa-solid fa-plus"></i>
                        Add Marks
                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>

<?php

mysqli_close($conn);

?>