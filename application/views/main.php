<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basic Order Form</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        form { max-width: 500px; margin: 0 auto; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 5px; margin-top: 5px; }
        button { margin-top: 20px; padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <h1>Basic Order Form</h1>
    
    <?php echo form_open('order/create'); ?>
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="phone">Phone:</label>
        <input type="tel" id="phone" name="phone" required>

        <label for="product">Product:</label>
        <select id="product" name="product" required>
            <option value="">Select a product</option>
            <option value="product1">Product 1 - RM10</option>
            <option value="product2">Product 2 - RM20</option>
            <option value="product3">Product 3 - RM30</option>
        </select>

        <label for="quantity">Quantity:</label>
        <input type="number" id="quantity" name="quantity" min="1" required>

        <button type="submit">Place Order</button>
    <?php echo form_close(); ?>

</body>
</html>