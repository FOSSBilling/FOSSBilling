<?php 
header("Location: " . pathinfo($_SERVER["PHP_SELF"], PATHINFO_DIRNAME) . "/install.php");
exit();