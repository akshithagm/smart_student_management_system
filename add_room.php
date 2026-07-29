<?php

session_start();
include "db_connect.php";

/* ==============================
   ADMIN SESSION CHECK
============================== */

if (
    !isset($_SESSION["admin_logged_in"]) ||
    $_SESSION["admin_logged_in"] !== true
) {
    header("Location: admin_login.html");
    exit();
}

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$adminId   = $_SESSION["admin_id"] ?? "Admin";

$errorMessage = "";

$roomNumber = "";
$roomName = "";
$roomType = "";
$capacity = "";
$floor = "";
$roomStatus = "Available";

/* ==============================
   ADD ROOM
============================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $roomNumber = trim($_POST["room_number"] ?? "");
    $roomName = trim($_POST["room_name"] ?? "");
    $roomType = trim($_POST["room_type"] ?? "");
    $capacity = trim($_POST["capacity"] ?? "");
    $floor = trim($_POST["floor"] ?? "");
    $roomStatus = trim($_POST["room_status"] ?? "");

    $allowedRoomTypes = [
        "Classroom",
        "Lab",
        "Seminar Hall"
    ];

    $allowedStatuses = [
        "Available",
        "Occupied",
        "Maintenance"
    ];

    if (
        $roomNumber === "" ||
        $roomName === "" ||
        $roomType === "" ||
        $capacity === "" ||
        $floor === "" ||
        $roomStatus === ""
    ) {
        $errorMessage = "Please fill in all the required fields.";
    } elseif (!in_array($roomType, $allowedRoomTypes, true)) {
        $errorMessage = "Please select a valid room type.";
    } elseif (!in_array($roomStatus, $allowedStatuses, true)) {
        $errorMessage = "Please select a valid room status.";
    } elseif (!ctype_digit($capacity) || (int) $capacity <= 0) {
        $errorMessage = "Capacity must be a positive whole number.";
    } else {

        /* CHECK DUPLICATE ROOM NUMBER */

        $duplicateStatement = mysqli_prepare(
            $conn,
            "
            SELECT id
            FROM rooms
            WHERE room_number = ?
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param(
            $duplicateStatement,
            "s",
            $roomNumber
        );

        mysqli_stmt_execute($duplicateStatement);

        $duplicateResult =
            mysqli_stmt_get_result($duplicateStatement);

        if (mysqli_num_rows($duplicateResult) > 0) {
            $errorMessage = "This room number already exists.";
        }

        mysqli_stmt_close($duplicateStatement);

        /* INSERT ROOM */

        if ($errorMessage === "") {

            $capacityValue = (int) $capacity;

            $insertStatement = mysqli_prepare(
                $conn,
                "
                INSERT INTO rooms
                (
                    room_number,
                    room_name,
                    room_type,
                    capacity,
                    floor,
                    room_status
                )
                VALUES (?, ?, ?, ?, ?, ?)
                "
            );

            mysqli_stmt_bind_param(
                $insertStatement,
                "sssiss",
                $roomNumber,
                $roomName,
                $roomType,
                $capacityValue,
                $floor,
                $roomStatus
            );

            if (mysqli_stmt_execute($insertStatement)) {

                mysqli_stmt_close($insertStatement);

                header("Location: manage_rooms.php?status=added");
                exit();

            } else {
                $errorMessage =
                    "Unable to add the room. Please try again.";
            }

            mysqli_stmt_close($insertStatement);
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

    <title>Add Room</title>

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

        button,
        input,
        select {
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
            font-size: 27px;
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
            transform: translateY(-1px);
            background: rgba(0, 212, 255, 0.12);
        }

        .form-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 22px;
            align-items: start;
        }

        .form-card,
        .preview-card {
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
            margin-bottom: 5px;
            font-size: 16px;
        }

        .card-header p {
            color: var(--muted);
            font-size: 10px;
        }

        .form-body {
            padding: 22px;
        }

        .error-message {
            display: flex;
            align-items: center;
            gap: 10px;

            margin-bottom: 18px;
            padding: 12px 14px;

            color: var(--red);
            background: rgba(255, 100, 124, 0.08);
            border: 1px solid rgba(255, 100, 124, 0.16);
            border-radius: 10px;

            font-size: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;

            color: #c7d3e1;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            top: 50%;
            left: 14px;

            color: var(--muted);
            font-size: 12px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            padding: 12px 14px 12px 40px;

            color: var(--text);
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 10px;
            outline: none;

            font-size: 11px;
            transition: 0.2s;
        }

        .input-wrapper select {
            cursor: pointer;
        }

        .input-wrapper option {
            color: #111;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            border-color: rgba(0, 212, 255, 0.42);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.06);
        }

        .form-help {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 8px;
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
        .submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 11px 16px;
            border-radius: 10px;

            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s;
        }

        .cancel-button {
            color: #b6c3d3;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
        }

        .submit-button {
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
        .submit-button:hover {
            transform: translateY(-1px);
        }

        .preview-content {
            padding: 22px;
            text-align: center;
        }

        .room-preview-icon {
            display: grid;
            place-items: center;

            width: 78px;
            height: 78px;
            margin: 0 auto 17px;

            color: var(--primary);
            background:
                linear-gradient(
                    135deg,
                    rgba(0, 212, 255, 0.13),
                    rgba(167, 139, 250, 0.09)
                );
            border: 1px solid rgba(0, 212, 255, 0.18);
            border-radius: 21px;

            font-size: 27px;
        }

        .preview-content h3 {
            margin-bottom: 5px;
            font-size: 19px;
        }

        .preview-content > p {
            margin-bottom: 18px;
            color: var(--muted);
            font-size: 10px;
        }

        .preview-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: left;
        }

        .preview-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;

            padding: 11px;

            background: rgba(255, 255, 255, 0.022);
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .preview-item span {
            color: var(--muted);
            font-size: 8px;
            text-transform: uppercase;
        }

        .preview-item strong {
            max-width: 155px;
            overflow: hidden;
            font-size: 9px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-preview {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background: var(--green);
            border-radius: 50%;
        }

        @media (max-width: 1000px) {

            .form-layout {
                grid-template-columns: 1fr;
            }

            .preview-card {
                order: -1;
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

        @media (max-width: 620px) {

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

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .cancel-button,
            .submit-button {
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

            <a href="manage_marks.php">
                <i class="fa-solid fa-square-poll-vertical"></i>
                <span>Internal Marks</span>
            </a>

            <a
                href="manage_rooms.php"
                class="active"
            >
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
                    Room Management
                </p>

                <h1>Add New Room</h1>

                <span>
                    Enter the room information and save it to the system.
                </span>

            </div>

            <a
                href="manage_rooms.php"
                class="back-button"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Rooms
            </a>

        </header>

        <div class="form-layout">

            <section class="form-card">

                <div class="card-header">

                    <h2>Room Information</h2>

                    <p>
                        All fields are required to create a complete room record.
                    </p>

                </div>

                <div class="form-body">

                    <?php if ($errorMessage !== ""): ?>

                        <div class="error-message">

                            <i class="fa-solid fa-circle-exclamation"></i>

                            <?php
                            echo htmlspecialchars($errorMessage);
                            ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="POST"
                        action=""
                        id="addRoomForm"
                    >

                        <div class="form-grid">

                            <div class="form-group">

                                <label for="room_number">
                                    Room Number
                                </label>

                                <div class="input-wrapper">

                                    <i class="fa-solid fa-hashtag"></i>

                                    <input
                                        type="text"
                                        id="room_number"
                                        name="room_number"
                                        maxlength="20"
                                        placeholder="Example: A101"
                                        value="<?php
                                        echo htmlspecialchars($roomNumber);
                                        ?>"
                                        required
                                    >

                                </div>

                                <span class="form-help">
                                    Room number must be unique.
                                </span>

                            </div>

                            <div class="form-group">

                                <label for="room_name">
                                    Room Name
                                </label>

                                <div class="input-wrapper">

                                    <i class="fa-solid fa-tag"></i>

                                    <input
                                        type="text"
                                        id="room_name"
                                        name="room_name"
                                        maxlength="100"
                                        placeholder="Example: Computer Science Lab"
                                        value="<?php
                                        echo htmlspecialchars($roomName);
                                        ?>"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="form-group">

                                <label for="room_type">
                                    Room Type
                                </label>

                                <div class="input-wrapper">

                                    <i class="fa-solid fa-book-open"></i>

                                    <select
                                        id="room_type"
                                        name="room_type"
                                        required
                                    >

                                        <option value="">
                                            Select Room Type
                                        </option>

                                        <option
                                            value="Classroom"
                                            <?php
                                            echo $roomType === "Classroom"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Classroom
                                        </option>

                                        <option
                                            value="Lab"
                                            <?php
                                            echo $roomType === "Lab"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Lab
                                        </option>

                                        <option
                                            value="Seminar Hall"
                                            <?php
                                            echo $roomType === "Seminar Hall"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Seminar Hall
                                        </option>

                                    </select>

                                </div>

                            </div>

                            <div class="form-group">

                                <label for="capacity">
                                    Capacity
                                </label>

                                <div class="input-wrapper">

                                    <i class="fa-solid fa-users"></i>

                                    <input
                                        type="number"
                                        id="capacity"
                                        name="capacity"
                                        min="1"
                                        step="1"
                                        placeholder="Example: 60"
                                        value="<?php
                                        echo htmlspecialchars($capacity);
                                        ?>"
                                        required
                                    >

                                </div>

                                <span class="form-help">
                                    Enter the maximum number of students.
                                </span>

                            </div>

                            <div class="form-group">

                                <label for="floor">
                                    Floor
                                </label>

                                <div class="input-wrapper">

                                    <i class="fa-solid fa-layer-group"></i>

                                    <input
                                        type="text"
                                        id="floor"
                                        name="floor"
                                        maxlength="20"
                                        placeholder="Example: First Floor"
                                        value="<?php
                                        echo htmlspecialchars($floor);
                                        ?>"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="form-group">

                                <label for="room_status">
                                    Room Status
                                </label>

                                <div class="input-wrapper">

                                    <i class="fa-solid fa-circle-check"></i>

                                    <select
                                        id="room_status"
                                        name="room_status"
                                        required
                                    >

                                        <option
                                            value="Available"
                                            <?php
                                            echo $roomStatus === "Available"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Available
                                        </option>

                                        <option
                                            value="Occupied"
                                            <?php
                                            echo $roomStatus === "Occupied"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Occupied
                                        </option>

                                        <option
                                            value="Maintenance"
                                            <?php
                                            echo $roomStatus === "Maintenance"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Maintenance
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>

                        <div class="form-actions">

                            <a
                                href="manage_rooms.php"
                                class="cancel-button"
                            >
                                <i class="fa-solid fa-xmark"></i>
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="submit-button"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Add Room
                            </button>

                        </div>

                    </form>

                </div>

            </section>

            <aside class="preview-card">

                <div class="card-header">

                    <h2>Live Preview</h2>

                    <p>
                        Preview of the room information.
                    </p>

                </div>

                <div class="preview-content">

                    <div class="room-preview-icon">
                        <i class="fa-solid fa-door-open"></i>
                    </div>

                    <h3 id="previewRoomNumber">
                        Room Number
                    </h3>

                    <p id="previewRoomName">
                        Room name will appear here
                    </p>

                    <div class="preview-list">

                        <div class="preview-item">
                            <span>Type</span>
                            <strong id="previewRoomType">
                                Not selected
                            </strong>
                        </div>

                        <div class="preview-item">
                            <span>Capacity</span>
                            <strong id="previewCapacity">
                                0 Students
                            </strong>
                        </div>

                        <div class="preview-item">
                            <span>Floor</span>
                            <strong id="previewFloor">
                                Not entered
                            </strong>
                        </div>

                        <div class="preview-item">
                            <span>Status</span>

                            <strong class="status-preview">

                                <span
                                    class="status-dot"
                                    id="statusDot"
                                ></span>

                                <span id="previewStatus">
                                    Available
                                </span>

                            </strong>

                        </div>

                    </div>

                </div>

            </aside>

        </div>

    </main>

    <script>

        const roomNumberInput =
            document.getElementById("room_number");

        const roomNameInput =
            document.getElementById("room_name");

        const roomTypeInput =
            document.getElementById("room_type");

        const capacityInput =
            document.getElementById("capacity");

        const floorInput =
            document.getElementById("floor");

        const roomStatusInput =
            document.getElementById("room_status");

        const previewRoomNumber =
            document.getElementById("previewRoomNumber");

        const previewRoomName =
            document.getElementById("previewRoomName");

        const previewRoomType =
            document.getElementById("previewRoomType");

        const previewCapacity =
            document.getElementById("previewCapacity");

        const previewFloor =
            document.getElementById("previewFloor");

        const previewStatus =
            document.getElementById("previewStatus");

        const statusDot =
            document.getElementById("statusDot");

        function updatePreview() {

            previewRoomNumber.textContent =
                roomNumberInput.value.trim() ||
                "Room Number";

            previewRoomName.textContent =
                roomNameInput.value.trim() ||
                "Room name will appear here";

            previewRoomType.textContent =
                roomTypeInput.value ||
                "Not selected";

            previewCapacity.textContent =
                capacityInput.value
                    ? capacityInput.value + " Students"
                    : "0 Students";

            previewFloor.textContent =
                floorInput.value.trim() ||
                "Not entered";

            previewStatus.textContent =
                roomStatusInput.value;

            if (roomStatusInput.value === "Available") {
                statusDot.style.background = "#30d98b";
            } else if (
                roomStatusInput.value === "Occupied"
            ) {
                statusDot.style.background = "#ff647c";
            } else {
                statusDot.style.background = "#ffc857";
            }

        }

        [
            roomNumberInput,
            roomNameInput,
            roomTypeInput,
            capacityInput,
            floorInput,
            roomStatusInput
        ].forEach(function (field) {

            field.addEventListener(
                "input",
                updatePreview
            );

            field.addEventListener(
                "change",
                updatePreview
            );

        });

        updatePreview();

    </script>

</body>
</html>

<?php

mysqli_close($conn);

?>