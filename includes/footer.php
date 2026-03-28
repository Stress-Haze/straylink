<?php
// includes/footer.php
// Set $nav_depth before including (same convention as navbar.php).
$depth = $nav_depth ?? 1; // 1 = pages/, 2 = dashboard/role/
$root  = str_repeat('../', $depth);
?>

<footer class="bg-success text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-1">StrayLink — Connecting strays with loving homes across Nepal</p>
        <small>Built by the community for animal welfare</small>
    </div>
</footer>
