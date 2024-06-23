<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success</title>
</head>
<body>
    <h1>Order Successful</h1>
    <p>Thank you for your order, <?php echo $order->name; ?>!</p>
    <p>Your order (ID: <?php echo $order->id; ?>) has been successfully paid.</p>
    <p>We will process your order shortly.</p>
</body>
</html>

