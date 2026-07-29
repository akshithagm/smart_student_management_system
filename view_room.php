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

$roomId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($roomId <= 0) {
    header("Location: manage_rooms.php");
    exit();
}

/* ==============================
   FETCH ROOM DETAILS
============================== */

$roomStatement = mysqli_prepare(
    $conn,
    "
    SELECT
        id,
        room_number,
        room_name,
        room_type,
        capacity,
        floor,
        room_status,
        created_at
    FROM rooms
    WHERE id = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $roomStatement,
    "i",
    $roomId
);

mysqli_stmt_execute($roomStatement);

$roomResult = mysqli_stmt_get_result($roomStatement);

if (mysqli_num_rows($roomResult) === 0) {
    mysqli_stmt_close($roomStatement);
    header("Location: manage_rooms.php");
    exit();
}

$room = mysqli_fetch_assoc($roomResult);

mysqli_stmt_close($roomStatement);

/* ==============================
   HELPER FUNCTIONS
============================== */

function getRoomStatusClass($status)
{
    if ($status === "Available") {
        return "available";
    }

    if ($status === "Occupied") {
        return "occupied";
    }

    return "maintenance";
}

function getRoomStatusIcon($status)
{
    if ($status === "Available") {
        return "fa-circle-check";
    }

    if ($status === "Occupied") {
        return "fa-user-group";
    }

    return "fa-screwdriver-wrench";
}

$roomStatusClass = getRoomStatusClass($room["room_status"]);
$roomStatusIcon  = getRoomStatusIcon($room["room_status"]);

$createdDate = "Not Available";

if (!empty($room["created_at"])) {
    $createdDate = date(
        "d M Y, h:i A",
        strtotime($room["created_at"])
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

    <title>
        Room Details - <?php
        echo htmlspecialchars($room["room_number"]);
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
            font-size: 27px;
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

        .room-hero {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 22px;

            margin-bottom: 22px;
            padding: 26px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            backdrop-filter: blur(18px);
        }

        .room-avatar {
            display: grid;
            place-items: center;

            width: 82px;
            height: 82px;

            color: var(--primary);
            background:
                linear-gradient(
                    135deg,
                    rgba(0, 212, 255, 0.13),
                    rgba(167, 139, 250, 0.09)
                );
            border: 1px solid rgba(0, 212, 255, 0.18);
            border-radius: 22px;

            font-size: 30px;
        }

        .room-heading h2 {
            margin-bottom: 7px;
            font-size: 22px;
        }

        .room-heading > p {
            margin-bottom: 15px;
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
        }

        .room-meta {
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

        .status-panel {
            min-width: 180px;
            text-align: right;
        }

        .status-panel small {
            display: block;
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 9px 13px;
            border-radius: 999px;

            font-size: 10px;
            font-weight: 700;
        }

        .status-badge.available {
            color: var(--green);
            background: rgba(48, 217, 139, 0.09);
            border: 1px solid rgba(48, 217, 139, 0.16);
        }

        .status-badge.occupied {
            color: var(--red);
            background: rgba(255, 100, 124, 0.09);
            border: 1px solid rgba(255, 100, 124, 0.16);
        }

        .status-badge.maintenance {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.09);
            border: 1px solid rgba(255, 200, 87, 0.16);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .summary-card {
            padding: 20px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            backdrop-filter: blur(18px);
        }

        .summary-icon {
            display: grid;
            place-items: center;

            width: 39px;
            height: 39px;
            margin-bottom: 14px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 10px;
        }

        .summary-card:nth-child(2) .summary-icon {
            color: var(--blue);
            background: rgba(98, 168, 255, 0.08);
        }

        .summary-card:nth-child(3) .summary-icon {
            color: var(--purple);
            background: rgba(167, 139, 250, 0.08);
        }

        .summary-card:nth-child(4) .summary-icon {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
        }

        .summary-card h3 {
            margin-bottom: 5px;
            font-size: 18px;
        }

        .summary-card p {
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .details-card {
            overflow: hidden;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .details-header {
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
        }

        .details-header h2 {
            margin-bottom: 5px;
            font-size: 16px;
        }

        .details-header p {
            color: var(--muted);
            font-size: 10px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 14px;

            padding: 21px 22px;
            border-right: 1px solid rgba(127, 211, 255, 0.08);
            border-bottom: 1px solid rgba(127, 211, 255, 0.08);
        }

        .detail-item:nth-child(even) {
            border-right: none;
        }

        .detail-item:nth-last-child(-n + 2) {
            border-bottom: none;
        }

        .detail-icon {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 42px;
            height: 42px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border-radius: 11px;
        }

        .detail-item small {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 8px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .detail-item strong {
            font-size: 11px;
        }

        @media (max-width: 1050px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .room-hero {
                grid-template-columns: auto 1fr;
            }

            .status-panel {
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

            .room-hero {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .room-avatar {
                margin: auto;
            }

            .room-meta {
                justify-content: center;
            }

            .status-panel {
                text-align: center;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .detail-item,
            .detail-item:nth-child(even),
            .detail-item:nth-last-child(-n + 2) {
                border-right: none;
                border-bottom: 1px solid rgba(127, 211, 255, 0.08);
            }

            .detail-item:last-child {
                border-bottom: none;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }
        }

        @media print {

            body {
                color: #111;
                background: #fff;
            }

            .admin-sidebar,
            .header-actions {
                display: none !important;
            }

            .admin-main {
                margin-left: 0;
                padding: 0;
            }

            .page-header h1,
            .room-heading h2,
            .summary-card h3,
            .details-header h2,
            .detail-item strong {
                color: #111;
            }

            .page-header span,
            .room-heading > p,
            .summary-card p,
            .details-header p,
            .detail-item small {
                color: #555;
            }

            .room-hero,
            .summary-card,
            .details-card {
                background: #fff;
                border: 1px solid #d8d8d8;
                box-shadow: none;
            }

            .room-avatar,
            .detail-icon,
            .summary-icon {
                color: #111;
                background: #f3f3f3;
                border-color: #ddd;
            }

            .status-badge {
                border: 1px solid #ccc;
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

                <h1>Room Details</h1>

                <span>
                    Complete information about the selected room.
                </span>

            </div>

            <div class="header-actions">

                <a
                    href="manage_rooms.php"
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
                    Print Details
                </button>

            </div>

        </header>

        <section class="room-hero">

            <div class="room-avatar">
                <i class="fa-solid fa-door-open"></i>
            </div>

            <div class="room-heading">

                <h2>
                    <?php
                    echo htmlspecialchars($room["room_number"]);
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars($room["room_name"]);
                    ?>
                </p>

                <div class="room-meta">

                    <span class="meta-chip">
                        <i class="fa-solid fa-layer-group"></i>
                        <?php
                        echo htmlspecialchars($room["room_type"]);
                        ?>
                    </span>

                    <span class="meta-chip">
                        <i class="fa-solid fa-building"></i>
                        <?php
                        echo htmlspecialchars($room["floor"]);
                        ?>
                    </span>

                    <span class="meta-chip">
                        <i class="fa-solid fa-users"></i>
                        Capacity:
                        <?php
                        echo (int) $room["capacity"];
                        ?>
                    </span>

                </div>

            </div>

            <div class="status-panel">

                <small>Current Status</small>

                <span
                    class="status-badge <?php
                    echo $roomStatusClass;
                    ?>"
                >
                    <i class="fa-solid <?php
                    echo $roomStatusIcon;
                    ?>"></i>

                    <?php
                    echo htmlspecialchars($room["room_status"]);
                    ?>
                </span>

            </div>

        </section>

        <section class="summary-grid">

            <article class="summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-hashtag"></i>
                </div>

                <h3>
                    <?php
                    echo htmlspecialchars($room["room_number"]);
                    ?>
                </h3>

                <p>Room Number</p>

            </article>

            <article class="summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-book-open"></i>
                </div>

                <h3>
                    <?php
                    echo htmlspecialchars($room["room_type"]);
                    ?>
                </h3>

                <p>Room Type</p>

            </article>

            <article class="summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <h3>
                    <?php
                    echo (int) $room["capacity"];
                    ?>
                </h3>

                <p>Maximum Capacity</p>

            </article>

            <article class="summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </div>

                <h3>
                    <?php
                    echo htmlspecialchars($room["floor"]);
                    ?>
                </h3>

                <p>Floor</p>

            </article>

        </section>

        <section class="details-card">

            <div class="details-header">

                <h2>Complete Room Information</h2>

                <p>
                    All stored information for this room record.
                </p>

            </div>

            <div class="details-grid">

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-fingerprint"></i>
                    </div>

                    <div>
                        <small>Database ID</small>
                        <strong>
                            <?php echo (int) $room["id"]; ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-hashtag"></i>
                    </div>

                    <div>
                        <small>Room Number</small>
                        <strong>
                            <?php
                            echo htmlspecialchars($room["room_number"]);
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-tag"></i>
                    </div>

                    <div>
                        <small>Room Name</small>
                        <strong>
                            <?php
                            echo htmlspecialchars($room["room_name"]);
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </div>

                    <div>
                        <small>Room Type</small>
                        <strong>
                            <?php
                            echo htmlspecialchars($room["room_type"]);
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div>
                        <small>Maximum Capacity</small>
                        <strong>
                            <?php
                            echo (int) $room["capacity"];
                            ?> Students
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <div>
                        <small>Floor</small>
                        <strong>
                            <?php
                            echo htmlspecialchars($room["floor"]);
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid <?php
                        echo $roomStatusIcon;
                        ?>"></i>
                    </div>

                    <div>
                        <small>Room Status</small>
                        <strong>
                            <?php
                            echo htmlspecialchars($room["room_status"]);
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-icon">
                        <i class="fa-solid fa-calendar-plus"></i>
                    </div>

                    <div>
                        <small>Record Created</small>
                        <strong>
                            <?php
                            echo htmlspecialchars($createdDate);
                            ?>
                        </strong>
                    </div>

                </div>

            </div>

        </section>

    </main>

</body>
</html>

<?php

mysqli_close($conn);

?>