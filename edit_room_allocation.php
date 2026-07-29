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
$error="";

if($_SERVER["REQUEST_METHOD"]=="POST"){

    $room_number=trim($_POST["room_number"]);
    $class_name=trim($_POST["class_name"]);
    $teacher_name=trim($_POST["teacher_name"]);
    $time_slot=trim($_POST["time_slot"]);
    $status=$_POST["status"];

    if($room_number=="" || $class_name=="" || $teacher_name=="" || $time_slot==""){
        $error="All fields are required.";
    }else{

        $update=mysqli_prepare($conn,"
        UPDATE room_allocations
        SET room_number=?,
            class_name=?,
            teacher_name=?,
            time_slot=?,
            status=?
        WHERE id=?");

        mysqli_stmt_bind_param(
            $update,
            "sssssi",
            $room_number,
            $class_name,
            $teacher_name,
            $time_slot,
            $status,
            $id
        );

        if(mysqli_stmt_execute($update)){
            header("Location: manage_room_allocation.php?status=updated");
            exit();
        }else{
            $error="Unable to update record.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit Room Allocation</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body{margin:0;background:#08111f;font-family:Arial;color:#fff}
.box{width:620px;max-width:92%;margin:40px auto;background:#10213b;padding:30px;border-radius:15px}
h2{text-align:center}
label{display:block;margin-top:15px}
input,select{width:100%;padding:12px;margin-top:6px;border:1px solid #355;background:#172b47;color:#fff;border-radius:8px}
.btns{display:flex;gap:10px;margin-top:25px}
button,a{flex:1;padding:12px;text-align:center;border-radius:8px;text-decoration:none;font-weight:bold}
button{background:#00d4ff;border:none;color:#00111a;cursor:pointer}
a{background:#444;color:#fff}
.error{background:#5a1d1d;padding:10px;border-radius:8px;margin-bottom:15px}
</style>
</head>
<body>

<div class="box">
<h2><i class="fa-solid fa-pen"></i> Edit Room Allocation</h2>

<?php if($error!=""){ ?><div class="error"><?=$error?></div><?php } ?>

<form method="post">

<label>Room Number</label>
<input type="text" name="room_number" value="<?=htmlspecialchars($row["room_number"])?>" required>

<label>Class Name</label>
<input type="text" name="class_name" value="<?=htmlspecialchars($row["class_name"])?>" required>

<label>Teacher Name</label>
<input type="text" name="teacher_name" value="<?=htmlspecialchars($row["teacher_name"])?>" required>

<label>Time Slot</label>
<input type="text" name="time_slot" value="<?=htmlspecialchars($row["time_slot"])?>" required>

<label>Status</label>
<select name="status">
<option value="Allocated" <?=$row["status"]=="Allocated"?"selected":""?>>Allocated</option>
<option value="Available" <?=$row["status"]=="Available"?"selected":""?>>Available</option>
</select>

<div class="btns">
<button type="submit"><i class="fa-solid fa-floppy-disk"></i> Update</button>
<a href="manage_room_allocation.php"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
</div>

</form>

</div>

</body>
</html>
