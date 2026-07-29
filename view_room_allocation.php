<?php
session_start();
include "db_connect.php";

if(!isset($_SESSION["admin_logged_in"])){
    header("Location: admin_login.html");
    exit();
}

if(!isset($_GET["id"])){
    header("Location: manage_room_allocation.php");
    exit();
}

$id=(int)$_GET["id"];

$stmt=mysqli_prepare($conn,"SELECT * FROM room_allocations WHERE id=?");
mysqli_stmt_bind_param($stmt,"i",$id);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)==0){
    header("Location: manage_room_allocation.php");
    exit();
}

$row=mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>View Room Allocation</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body{margin:0;font-family:Arial;background:#08111f;color:#fff}
.card{width:650px;max-width:92%;margin:40px auto;background:#10213b;padding:30px;border-radius:15px}
h2{text-align:center;margin-bottom:25px}
table{width:100%;border-collapse:collapse}
td{padding:14px;border-bottom:1px solid #28415f}
td:first-child{font-weight:bold;width:220px}
.badge{padding:6px 12px;border-radius:20px;font-weight:bold}
.Allocated{background:#154d34;color:#6cffb3}
.Available{background:#66510d;color:#ffd84f}
.btns{display:flex;gap:10px;margin-top:25px}
a{flex:1;text-align:center;padding:12px;border-radius:8px;text-decoration:none;font-weight:bold}
.back{background:#444;color:#fff}
.edit{background:#00d4ff;color:#00111a}
</style>
</head>
<body>
<div class="card">
<h2><i class="fa-solid fa-eye"></i> View Room Allocation</h2>

<table>
<tr><td>Room Number</td><td><?=htmlspecialchars($row["room_number"])?></td></tr>
<tr><td>Class Name</td><td><?=htmlspecialchars($row["class_name"])?></td></tr>
<tr><td>Teacher Name</td><td><?=htmlspecialchars($row["teacher_name"])?></td></tr>
<tr><td>Time Slot</td><td><?=htmlspecialchars($row["time_slot"])?></td></tr>
<tr><td>Status</td><td><span class="badge <?=$row["status"]?>"><?=$row["status"]?></span></td></tr>
<tr><td>Created At</td><td><?=htmlspecialchars($row["created_at"])?></td></tr>
<tr><td>Updated At</td><td><?=htmlspecialchars($row["updated_at"])?></td></tr>
</table>

<div class="btns">
<a class="back" href="manage_room_allocation.php"><i class="fa-solid fa-arrow-left"></i> Back</a>
<a class="edit" href="edit_room_allocation.php?id=<?=$row["id"]?>"><i class="fa-solid fa-pen"></i> Edit</a>
</div>

</div>
</body>
</html>