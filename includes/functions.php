<?php
// includes/functions.php

function calculateVacationQuota($startDate) {
    if (empty($startDate)) return 6;

    $start = new DateTime($startDate);
    $today = new DateTime();
    $years = $start->diff($today)->y; 

    if ($years < 1) return 0;

    return min(6 + ($years - 1), 10);
}