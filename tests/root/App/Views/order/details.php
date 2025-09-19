<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    order details
    <table>
        <?php foreach ($rows ?? [] as $row) { ?>
            <?= Cube\render("order/row", ['row' => $row]) ?>
        <?php }  ?>
    </table>
</body>
</html>