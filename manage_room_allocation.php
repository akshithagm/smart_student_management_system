<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: admin_login.html");
    exit();
}

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$adminId   = $_SESSION["admin_id"] ?? "Admin";

$statusMessage = "";
if (isset($_GET["status"])) {
    if ($_GET["status"] === "added") {
        $statusMessage = "Room allocation added successfully.";
    } elseif ($_GET["status"] === "updated") {
        $statusMessage = "Room allocation updated successfully.";
    }
}

$totalAllocations = 0;
$allocatedCount = 0;
$availableCount = 0;
$totalTeachers = 0;

$statsQuery = "
    SELECT
        COUNT(*) AS total_allocations,
        SUM(status = 'Allocated') AS allocated_count,
        SUM(status = 'Available') AS available_count,
        COUNT(DISTINCT teacher_name) AS total_teachers
    FROM room_allocations
";

$statsResult = mysqli_query($conn, $statsQuery);

if ($statsResult) {
    $stats = mysqli_fetch_assoc($statsResult);
    $totalAllocations = (int)($stats["total_allocations"] ?? 0);
    $allocatedCount   = (int)($stats["allocated_count"] ?? 0);
    $availableCount   = (int)($stats["available_count"] ?? 0);
    $totalTeachers    = (int)($stats["total_teachers"] ?? 0);
}

$allocations = [];

$listQuery = "
    SELECT
        id,
        room_number,
        class_name,
        teacher_name,
        time_slot,
        status,
        created_at,
        updated_at
    FROM room_allocations
    ORDER BY id DESC
";

$listResult = mysqli_query($conn, $listQuery);

if ($listResult) {
    while ($row = mysqli_fetch_assoc($listResult)) {
        $allocations[] = $row;
    }
}

function allocationStatusClass($status)
{
    return $status === "Allocated" ? "allocated" : "available";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Room Allocation</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            --bg:#050b18;
            --card:rgba(13,27,48,.84);
            --border:rgba(127,211,255,.13);
            --primary:#00d4ff;
            --text:#f5f9ff;
            --muted:#8192aa;
            --green:#30d98b;
            --red:#ff647c;
            --yellow:#ffc857;
            --blue:#62a8ff;
            --purple:#a78bfa;
        }
        body{
            min-height:100vh;
            color:var(--text);
            background:
                radial-gradient(circle at top right,rgba(0,212,255,.1),transparent 28%),
                radial-gradient(circle at bottom left,rgba(167,139,250,.08),transparent 30%),
                var(--bg);
            font-family:Arial,Helvetica,sans-serif;
        }
        a{text-decoration:none;color:inherit}
        .sidebar{
            position:fixed;left:0;top:0;z-index:20;
            width:255px;height:100vh;padding:24px 18px;
            display:flex;flex-direction:column;
            background:rgba(6,16,31,.96);
            border-right:1px solid var(--border);
            backdrop-filter:blur(20px);
        }
        .brand{display:flex;gap:12px;align-items:center;padding:0 7px 23px;border-bottom:1px solid var(--border)}
        .logo,.avatar{display:grid;place-items:center;color:#04101c;background:linear-gradient(135deg,var(--primary),#8af0ff)}
        .logo{width:45px;height:45px;border-radius:13px}
        .brand h2{font-size:19px}
        .brand span,.profile p{display:block;margin-top:3px;color:var(--muted);font-size:10px}
        .profile{
            display:flex;align-items:center;gap:11px;margin:22px 0;padding:14px;
            background:rgba(255,255,255,.025);border:1px solid var(--border);border-radius:14px;
        }
        .avatar{width:39px;height:39px;border-radius:11px;color:var(--primary);background:rgba(0,212,255,.09)}
        .profile h3{font-size:13px}
        .menu{display:flex;flex:1;flex-direction:column;gap:6px}
        .menu a,.bottom a{
            display:flex;align-items:center;gap:13px;padding:12px 14px;
            color:#97a6ba;border:1px solid transparent;border-radius:11px;
            font-size:12px;transition:.2s;
        }
        .menu a i,.bottom a i{width:18px;text-align:center}
        .menu a:hover,.menu a.active,.bottom a:hover{
            color:var(--text);background:rgba(0,212,255,.08);border-color:rgba(0,212,255,.12)
        }
        .menu a.active{color:var(--primary)}
        .bottom{display:flex;flex-direction:column;gap:5px;padding-top:15px;border-top:1px solid var(--border)}
        .main{min-height:100vh;margin-left:255px;padding:32px}
        .header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}
        .label{margin-bottom:7px;color:var(--primary);font-size:10px;font-weight:700;letter-spacing:1.7px;text-transform:uppercase}
        .header h1{font-size:27px;margin-bottom:7px}
        .header span{color:var(--muted);font-size:12px}
        .add-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:8px;
            padding:12px 17px;color:#03111c;
            background:linear-gradient(135deg,var(--primary),#78efff);
            border-radius:10px;font-size:10px;font-weight:700;
        }
        .message{
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:20px;padding:13px 15px;
            color:var(--green);background:rgba(48,217,139,.08);
            border:1px solid rgba(48,217,139,.16);border-radius:11px;font-size:10px;
        }
        .message button{color:var(--green);background:none;border:none;cursor:pointer}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:22px}
        .stat{
            padding:20px;background:var(--card);border:1px solid var(--border);
            border-radius:15px;backdrop-filter:blur(18px)
        }
        .stat-icon{
            display:grid;place-items:center;width:39px;height:39px;margin-bottom:14px;
            border-radius:10px;color:var(--primary);background:rgba(0,212,255,.08)
        }
        .stat:nth-child(2) .stat-icon{color:var(--green);background:rgba(48,217,139,.08)}
        .stat:nth-child(3) .stat-icon{color:var(--yellow);background:rgba(255,200,87,.08)}
        .stat:nth-child(4) .stat-icon{color:var(--purple);background:rgba(167,139,250,.08)}
        .stat h3{font-size:23px;margin-bottom:5px}
        .stat p{color:var(--muted);font-size:9px;letter-spacing:.8px;text-transform:uppercase}
        .card{
            overflow:hidden;background:var(--card);border:1px solid var(--border);
            border-radius:17px;backdrop-filter:blur(18px)
        }
        .card-header{
            display:flex;align-items:center;justify-content:space-between;gap:18px;
            padding:20px 22px;border-bottom:1px solid var(--border)
        }
        .card-header h2{font-size:16px;margin-bottom:5px}
        .card-header p{color:var(--muted);font-size:10px}
        .search{position:relative;width:310px}
        .search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px}
        .search input{
            width:100%;padding:11px 14px 11px 38px;color:var(--text);
            background:rgba(255,255,255,.025);border:1px solid var(--border);
            border-radius:10px;outline:none;font-size:10px
        }
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:15px 17px;border-bottom:1px solid rgba(127,211,255,.08);text-align:left;vertical-align:middle}
        th{color:#8293aa;background:rgba(255,255,255,.018);font-size:8px;letter-spacing:.9px;text-transform:uppercase;white-space:nowrap}
        td{font-size:10px;color:#dce6f3}
        tbody tr:hover{background:rgba(0,212,255,.025)}
        .room-cell{display:flex;align-items:center;gap:10px;min-width:135px}
        .room-icon{
            display:grid;place-items:center;width:34px;height:34px;border-radius:9px;
            color:var(--primary);background:rgba(0,212,255,.07)
        }
        .room-cell strong{display:block;margin-bottom:3px}
        .room-cell span{font-size:8px;color:var(--muted)}
        .status{
            display:inline-flex;align-items:center;gap:7px;
            padding:7px 10px;border-radius:999px;font-size:8px;font-weight:700
        }
        .status::before{content:"";width:6px;height:6px;border-radius:50%}
        .status.allocated{color:var(--green);background:rgba(48,217,139,.08);border:1px solid rgba(48,217,139,.14)}
        .status.allocated::before{background:var(--green)}
        .status.available{color:var(--yellow);background:rgba(255,200,87,.08);border:1px solid rgba(255,200,87,.14)}
        .status.available::before{background:var(--yellow)}
        .actions{display:flex;gap:7px}
        .action{
            display:inline-flex;align-items:center;gap:6px;padding:8px 10px;
            border-radius:8px;font-size:8px;font-weight:700
        }
        .view{color:var(--primary);background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.12)}
        .edit{color:var(--yellow);background:rgba(255,200,87,.06);border:1px solid rgba(255,200,87,.13)}
        .empty{padding:55px 20px;text-align:center}
        .empty i{font-size:36px;color:var(--muted);margin-bottom:14px}
        .empty h3{font-size:16px;margin-bottom:7px}
        .empty p{color:var(--muted);font-size:10px;margin-bottom:17px}
        .no-results{display:none}
        .no-results td{text-align:center;padding:35px;color:var(--muted)}
        @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:850px){
            .sidebar{position:static;width:100%;height:auto}
            .menu{display:grid;grid-template-columns:repeat(2,1fr)}
            .bottom{flex-direction:row}
            .main{margin-left:0;padding:22px}
            .card-header{align-items:stretch;flex-direction:column}
            .search{width:100%}
        }
        @media(max-width:620px){
            .header{align-items:stretch;flex-direction:column}
            .add-btn{width:100%}
            .stats{grid-template-columns:1fr}
            .menu{grid-template-columns:1fr}
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <div class="logo"><i class="fa-solid fa-graduation-cap"></i></div>
        <div>
            <h2>SSMS</h2>
            <span>Admin Portal</span>
        </div>
    </div>

    <div class="profile">
        <div class="avatar"><i class="fa-solid fa-user-shield"></i></div>
        <div>
            <h3><?php echo htmlspecialchars($adminName); ?></h3>
            <p><?php echo htmlspecialchars($adminId); ?></p>
        </div>
    </div>

    <nav class="menu">
        <a href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i>Dashboard</a>
        <a href="manage_students.php"><i class="fa-solid fa-user-graduate"></i>Manage Students</a>
        <a href="manage_attendance.php"><i class="fa-solid fa-calendar-check"></i>Attendance</a>
        <a href="manage_marks.php"><i class="fa-solid fa-square-poll-vertical"></i>Internal Marks</a>
        <a href="manage_rooms.php"><i class="fa-solid fa-building"></i>Manage Rooms</a>
        <a href="manage_room_allocation.php" class="active"><i class="fa-solid fa-door-open"></i>Room Allocation</a>
    </nav>

    <div class="bottom">
        <a href="index.php"><i class="fa-solid fa-house"></i>Home Page</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
    </div>
</aside>

<main class="main">

    <header class="header">
        <div>
            <p class="label">Room Allocation Management</p>
            <h1>Manage Room Allocation</h1>
            <span>View, search and update room allocation records.</span>
        </div>

        <a href="add_room_allocation.php" class="add-btn">
            <i class="fa-solid fa-plus"></i>
            Add Allocation
        </a>
    </header>

    <?php if ($statusMessage !== ""): ?>
        <div class="message" id="successMessage">
            <span>
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($statusMessage); ?>
            </span>
            <button type="button"
                    onclick="document.getElementById('successMessage').style.display='none'">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    <?php endif; ?>

    <section class="stats">
        <article class="stat">
            <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
            <h3><?php echo $totalAllocations; ?></h3>
            <p>Total Allocations</p>
        </article>

        <article class="stat">
            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h3><?php echo $allocatedCount; ?></h3>
            <p>Allocated</p>
        </article>

        <article class="stat">
            <div class="stat-icon"><i class="fa-solid fa-door-open"></i></div>
            <h3><?php echo $availableCount; ?></h3>
            <p>Available</p>
        </article>

        <article class="stat">
            <div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <h3><?php echo $totalTeachers; ?></h3>
            <p>Total Teachers</p>
        </article>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2>Allocation Directory</h2>
                <p>Search and manage all room allocation records.</p>
            </div>

            <div class="search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text"
                       id="allocationSearch"
                       placeholder="Search room, class, teacher or time slot...">
            </div>
        </div>

        <?php if (count($allocations) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Class Name</th>
                            <th>Teacher Name</th>
                            <th>Time Slot</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($allocations as $allocation): ?>
                        <?php
                        $searchText = strtolower(
                            ($allocation["room_number"] ?? "") . " " .
                            ($allocation["class_name"] ?? "") . " " .
                            ($allocation["teacher_name"] ?? "") . " " .
                            ($allocation["time_slot"] ?? "") . " " .
                            ($allocation["status"] ?? "")
                        );
                        ?>
                        <tr class="allocation-row"
                            data-search="<?php echo htmlspecialchars($searchText); ?>">

                            <td>
                                <div class="room-cell">
                                    <div class="room-icon">
                                        <i class="fa-solid fa-door-open"></i>
                                    </div>
                                    <div>
                                        <strong>
                                            <?php echo htmlspecialchars($allocation["room_number"]); ?>
                                        </strong>
                                        <span>Allocation #<?php echo (int)$allocation["id"]; ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($allocation["class_name"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($allocation["teacher_name"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($allocation["time_slot"]); ?>
                            </td>

                            <td>
                                <span class="status <?php
                                    echo allocationStatusClass($allocation["status"]);
                                ?>">
                                    <?php echo htmlspecialchars($allocation["status"]); ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">
                                    <a class="action view"
                                       href="view_room_allocation.php?id=<?php echo (int)$allocation["id"]; ?>">
                                        <i class="fa-solid fa-eye"></i>
                                        View
                                    </a>

                                    <a class="action edit"
                                       href="edit_room_allocation.php?id=<?php echo (int)$allocation["id"]; ?>">
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </a>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>

                        <tr id="noResults" class="no-results">
                            <td colspan="6">
                                No matching allocation records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <i class="fa-solid fa-calendar-xmark"></i>
                <h3>No Allocation Records</h3>
                <p>Add your first room allocation record.</p>
                <a href="add_room_allocation.php" class="add-btn">
                    <i class="fa-solid fa-plus"></i>
                    Add Allocation
                </a>
            </div>
        <?php endif; ?>
    </section>

</main>

<script>
    const searchInput = document.getElementById("allocationSearch");
    const rows = document.querySelectorAll(".allocation-row");
    const noResults = document.getElementById("noResults");

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const value = this.value.trim().toLowerCase();
            let visible = 0;

            rows.forEach(function (row) {
                const matches = (row.dataset.search || "").includes(value);
                row.style.display = matches ? "" : "none";

                if (matches) {
                    visible++;
                }
            });

            if (noResults) {
                noResults.style.display = visible === 0 ? "table-row" : "none";
            }
        });
    }
</script>

</body>
</html>

<?php mysqli_close($conn); ?>
