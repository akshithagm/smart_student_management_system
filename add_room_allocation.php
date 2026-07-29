<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["admin_logged_in"])) {
    header("Location: admin_login.html");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $room_number = trim($_POST["room_number"]);
    $class_name = trim($_POST["class_name"]);
    $teacher_name = trim($_POST["teacher_name"]);
    $time_slot = trim($_POST["time_slot"]);
    $status = $_POST["status"];

    if ($room_number=="" || $class_name=="" || $teacher_name=="" || $time_slot=="") {
        $error = "All fields are required.";
    } else {

        $stmt = mysqli_prepare($conn,
        "INSERT INTO room_allocations
        (room_number,class_name,teacher_name,time_slot,status)
        VALUES (?,?,?,?,?)");

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $room_number,
            $class_name,
            $teacher_name,
            $time_slot,
            $status
        );

        if(mysqli_stmt_execute($stmt)){
            header("Location: manage_room_allocation.php?status=added");
            exit();
        }else{
            $error="Unable to save record.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Room Allocation</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body{margin:0;font-family:Arial;background:#08111f;color:#fff}
.box{width:600px;max-width:92%;margin:40px auto;background:#10213b;padding:30px;border-radius:15px}
h2{text-align:center}
label{display:block;margin-top:15px}
input,select{width:100%;padding:12px;margin-top:6px;border-radius:8px;border:1px solid #345;background:#172b47;color:#fff}
.btns{display:flex;gap:10px;margin-top:25px}
button,a{flex:1;padding:12px;text-align:center;border-radius:8px;text-decoration:none;font-weight:bold}
button{background:#00d4ff;border:none;color:#00111a;cursor:pointer}
a{background:#444;color:#fff}
.error{background:#5a1d1d;padding:10px;border-radius:8px;margin-bottom:15px}
</style>
</head>
<body>

<div class="box">
<h2><i class="fa-solid fa-door-open"></i> Add Room Allocation</h2>

<?php if($error!=""){ ?>
<div class="error"><?php echo $error; ?></div>
<?php } ?>

<form method="post">

<label>Room Number</label>
<input type="text" name="room_number" required>

<label>Class Name</label>
<input type="text" name="class_name" required>

<label>Teacher Name</label>
<input type="text" name="teacher_name" required>

<label>Time Slot</label>
<input type="text" name="time_slot" placeholder="9:00 AM - 10:00 AM" required>

<label>Status</label>
<select name="status">
<option value="Allocated">Allocated</option>
<option value="Available">Available</option>
</select>

<div class="btns">
<button type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button>
<a href="manage_room_allocation.php"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
</div>

</form>
</div>

</body>
</html>
