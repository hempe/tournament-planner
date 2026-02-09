<?php
// Display flash messages
if (has_flash('error')) {
    $error = json_encode(get_flash('error'), JSON_HEX_TAG | JSON_HEX_AMP);
    echo "<script>document.addEventListener('DOMContentLoaded', () => customError($error));</script>";
}

if (has_flash('success')) {
    // For now, display success messages as a simple alert
    // You could create a customSuccess() function similar to customError()
    $success = json_encode(get_flash('success'), JSON_HEX_TAG | JSON_HEX_AMP);
    echo "<script>document.addEventListener('DOMContentLoaded', () => alert($success));</script>";
}
?>
</body>

</html>