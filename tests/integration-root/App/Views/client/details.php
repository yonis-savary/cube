<?php

use function Cube\render;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    client details

    <?php echo \Cube\asset('hello.js'); ?>
    <?php echo render('client/row'); ?>
</body>
</html>