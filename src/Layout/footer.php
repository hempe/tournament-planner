<?php
// Display flash messages
if (has_flash('error')) {
    $error = json_encode(get_flash('error'), JSON_HEX_TAG | JSON_HEX_AMP);
    echo "<script>document.addEventListener('DOMContentLoaded', () => customError($error));</script>";
}

if (has_flash('success')) {
    $success = json_encode(get_flash('success'), JSON_HEX_TAG | JSON_HEX_AMP);
    echo "<script>document.addEventListener('DOMContentLoaded', () => customSuccess($success));</script>";
}

if (has_flash('social_prompt')) {
    $prompt = json_encode(get_flash('social_prompt'), JSON_HEX_TAG | JSON_HEX_AMP);
    echo "<script>document.addEventListener('DOMContentLoaded', () => customSocialPrompt($prompt));</script>";
}
?>
</body>

</html>