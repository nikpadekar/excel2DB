<?php
require_once __DIR__ . "/mySQLConn.php";

?>
<!DOCTYPE html>
<html>
<body>

<form action="upload.php" method="post" enctype="multipart/form-data">
    Select Execel File With Template to upload:
    <input type="file" name="fileToUpload" id="fileToUpload">
    <input type="submit" value="Upload File" name="submit">
</form>

</body>
</html>