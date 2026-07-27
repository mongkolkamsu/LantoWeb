<?php
session_start();

// 🎯 ถ้ายังไม่ได้ล็อกอิน ให้ส่งกลับไปหน้า Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 🎯 ถ้าล็อกอินเรียบร้อยแล้ว ให้ดีดไปที่หน้าแดชบอร์ดหลักทันที (จบปัญหาโค้ดซ้ำซ้อน)
header("Location: index_mobile.php");
exit();