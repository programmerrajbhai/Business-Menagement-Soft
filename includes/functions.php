<?php
// includes/functions.php

// টাকার অংক সুন্দর করে দেখানোর জন্য (যেমন: 1500 কে ৳ 1,500.00 বানাবে)
function format_taka($amount) {
    return '৳ ' . number_format($amount, 2);
}

// ডেট ফরম্যাট করার জন্য (যেমন: 2026-02-25 কে 25-02-2026 বানাবে)
function format_date($date) {
    return date('d-M-Y', strtotime($date));
}
?>