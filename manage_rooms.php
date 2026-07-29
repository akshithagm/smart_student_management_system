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

/* ==============================
   FETCH ROOM STATISTICS
============================== */

$totalRooms = 0;
$availableRooms = 0;
$occupiedRooms = 0;
$maintenanceRooms = 0;

$statsQuery = "
    SELECT
        COUNT(*) AS total_rooms,
        SUM(room_status = 'Available') AS available_rooms,
        SUM(room_status = 'Occupied') AS occupied_rooms,
        SUM(room_status = 'Maintenance') AS maintenance_rooms
    FROM rooms
";

$statsResult = mysqli_query($conn, $statsQuery);

if ($statsResult) {
    $stats = mysqli_fetch_assoc($statsResult);

    $totalRooms = (int) ($stats["total_rooms"] ?? 0);
    $availableRooms = (int) ($stats["available_rooms"] ?? 0);
    $occupiedRooms = (int) ($stats["occupied_rooms"] ?? 0);
    $maintenanceRooms = (int) ($stats["maintenance_rooms"] ?? 0);
}

/* ==============================
   FETCH ALL ROOMS
============================== */

$rooms = [];

$roomsQuery = "
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
    ORDER BY room_number ASC
";

$roomsResult = mysqli_query($conn, $roomsQuery);

if ($roomsResult) {
    while ($room = mysqli_fetch_assoc($roomsResult)) {
        $rooms[] = $room;
    }
}

/* ==============================
   HELPER FUNCTION
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Rooms</title>

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

        .add-room-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 17px;
            color: #03111c;
            background: linear-gradient(135deg, var(--primary), #78efff);
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            transition: 0.25s;
        }

        .add-room-button:hover {
            transform: translateY(-1px);
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

            padding: 20px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            backdrop-filter: blur(18px);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            top: -26px;
            right: -26px;

            width: 78px;
            height: 78px;

            background: rgba(0, 212, 255, 0.05);
            border-radius: 50%;
        }

        .stat-icon {
            display: grid;
            place-items: center;

            width: 39px;
            height: 39px;
            margin-bottom: 14px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 10px;
        }

        .stat-card:nth-child(2) .stat-icon {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
        }

        .stat-card:nth-child(3) .stat-icon {
            color: var(--red);
            background: rgba(255, 100, 124, 0.08);
        }

        .stat-card:nth-child(4) .stat-icon {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.08);
        }

        .stat-card h3 {
            margin-bottom: 5px;
            font-size: 23px;
        }

        .stat-card p {
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .rooms-card {
            overflow: hidden;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .rooms-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;

            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
        }

        .rooms-card-header h2 {
            margin-bottom: 5px;
            font-size: 16px;
        }

        .rooms-card-header p {
            color: var(--muted);
            font-size: 10px;
        }

        .search-box {
            position: relative;
            width: 290px;
        }

        .search-box i {
            position: absolute;
            top: 50%;
            left: 14px;
            color: var(--muted);
            font-size: 12px;
            transform: translateY(-50%);
        }

        .search-box input {
            width: 100%;
            padding: 11px 14px 11px 38px;

            color: var(--text);
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 10px;
            outline: none;

            font-size: 10px;
            transition: 0.2s;
        }

        .search-box input:focus {
            border-color: rgba(0, 212, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.06);
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

        tbody tr {
            transition: 0.2s;
        }

        tbody tr:hover {
            background: rgba(0, 212, 255, 0.025);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .room-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 170px;
        }

        .room-icon {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 35px;
            height: 35px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border-radius: 9px;
        }

        .room-cell strong {
            display: block;
            margin-bottom: 3px;
            font-size: 10px;
        }

        .room-cell span {
            color: var(--muted);
            font-size: 8px;
        }

        .capacity-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 7px 9px;

            color: var(--blue);
            background: rgba(98, 168, 255, 0.08);
            border: 1px solid rgba(98, 168, 255, 0.13);
            border-radius: 8px;

            font-size: 9px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            padding: 7px 10px;
            border-radius: 999px;

            font-size: 8px;
            font-weight: 700;
        }

        .status-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status-badge.available {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
            border: 1px solid rgba(48, 217, 139, 0.14);
        }

        .status-badge.available::before {
            background: var(--green);
        }

        .status-badge.occupied {
            color: var(--red);
            background: rgba(255, 100, 124, 0.08);
            border: 1px solid rgba(255, 100, 124, 0.14);
        }

        .status-badge.occupied::before {
            background: var(--red);
        }

        .status-badge.maintenance {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.08);
            border: 1px solid rgba(255, 200, 87, 0.14);
        }

        .status-badge.maintenance::before {
            background: var(--yellow);
        }

        .view-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            padding: 8px 11px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.06);
            border: 1px solid rgba(0, 212, 255, 0.12);
            border-radius: 8px;

            font-size: 8px;
            font-weight: 700;
            transition: 0.2s;
        }

        .view-button:hover {
            background: rgba(0, 212, 255, 0.12);
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            gap: 7px;
        }

        .edit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 11px;
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.06);
            border: 1px solid rgba(255, 200, 87, 0.13);
            border-radius: 8px;
            font-size: 8px;
            font-weight: 700;
            transition: 0.2s;
        }

        .edit-button:hover {
            background: rgba(255, 200, 87, 0.12);
            transform: translateY(-1px);
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-state i {
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 38px;
        }

        .empty-state h3 {
            margin-bottom: 7px;
            font-size: 16px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 10px;
        }

        .no-results-row {
            display: none;
        }

        .no-results-row td {
            padding: 35px 20px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 1050px) {

            .statistics-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .rooms-card-header {
                align-items: stretch;
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }
        }

        @media (max-width: 620px) {

            .statistics-grid {
                grid-template-columns: 1fr;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
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

                <h1>Manage Rooms</h1>

                <span>
                    Add, view and update room information.
                </span>

            </div>

            <a href="add_room.php" class="add-room-button">
                <i class="fa-solid fa-plus"></i>
                Add New Room
            </a>

        </header>

        <section class="statistics-grid">

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-building"></i>
                </div>

                <h3><?php echo $totalRooms; ?></h3>
                <p>Total Rooms</p>

            </article>

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <h3><?php echo $availableRooms; ?></h3>
                <p>Available Rooms</p>

            </article>

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-user-group"></i>
                </div>

                <h3><?php echo $occupiedRooms; ?></h3>
                <p>Occupied Rooms</p>

            </article>

            <article class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>

                <h3><?php echo $maintenanceRooms; ?></h3>
                <p>Under Maintenance</p>

            </article>

        </section>

        <section class="rooms-card">

            <div class="rooms-card-header">

                <div>

                    <h2>Room Directory</h2>

                    <p>
                        Search and view all rooms available in the system.
                    </p>

                </div>

                <div class="search-box">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="roomSearch"
                        placeholder="Search room number, name, type or floor..."
                        autocomplete="off"
                    >

                </div>

            </div>

            <?php if (count($rooms) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>Room</th>
                                <th>Room Type</th>
                                <th>Capacity</th>
                                <th>Floor</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody id="roomsTableBody">

                            <?php foreach ($rooms as $room): ?>

                                <?php
                                $searchText = strtolower(
                                    ($room["room_number"] ?? "") . " " .
                                    ($room["room_name"] ?? "") . " " .
                                    ($room["room_type"] ?? "") . " " .
                                    ($room["floor"] ?? "") . " " .
                                    ($room["room_status"] ?? "")
                                );
                                ?>

                                <tr
                                    class="room-row"
                                    data-search="<?php
                                    echo htmlspecialchars($searchText);
                                    ?>"
                                >

                                    <td>

                                        <div class="room-cell">

                                            <div class="room-icon">
                                                <i class="fa-solid fa-door-open"></i>
                                            </div>

                                            <div>

                                                <strong>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $room["room_number"]
                                                    );
                                                    ?>
                                                </strong>

                                                <span>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $room["room_name"]
                                                    );
                                                    ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $room["room_type"]
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <span class="capacity-badge">

                                            <i class="fa-solid fa-users"></i>

                                            <?php
                                            echo (int) $room["capacity"];
                                            ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $room["floor"]
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <span
                                            class="status-badge <?php
                                            echo getRoomStatusClass(
                                                $room["room_status"]
                                            );
                                            ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $room["room_status"]
                                            );
                                            ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="action-buttons">

                                            <a
                                                href="view_room.php?id=<?php
                                                echo (int) $room["id"];
                                                ?>"
                                                class="view-button"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                                View
                                            </a>

                                            <a
                                                href="edit_room.php?id=<?php
                                                echo (int) $room["id"];
                                                ?>"
                                                class="edit-button"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                                Edit
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <tr
                                id="noSearchResults"
                                class="no-results-row"
                            >
                                <td colspan="6">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    No matching rooms found.
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-building-circle-xmark"></i>

                    <h3>No Rooms Found</h3>

                    <p>
                        Add room records through phpMyAdmin to display them here.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

    <script>

        const roomSearch =
            document.getElementById("roomSearch");

        const roomRows =
            document.querySelectorAll(".room-row");

        const noSearchResults =
            document.getElementById("noSearchResults");

        if (roomSearch) {

            roomSearch.addEventListener("input", function () {

                const searchValue =
                    this.value.trim().toLowerCase();

                let visibleRows = 0;

                roomRows.forEach(function (row) {

                    const searchableText =
                        row.dataset.search || "";

                    const matches =
                        searchableText.includes(searchValue);

                    row.style.display =
                        matches ? "" : "none";

                    if (matches) {
                        visibleRows++;
                    }

                });

                if (noSearchResults) {
                    noSearchResults.style.display =
                        visibleRows === 0
                            ? "table-row"
                            : "none";
                }

            });

        }

    </script>

</body>
</html>

<?php

mysqli_close($conn);

?>
