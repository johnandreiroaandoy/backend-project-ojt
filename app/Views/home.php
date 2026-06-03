<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 50px; background: #f4f6f9; color: #333; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-top: 0; }
        .badge { background: #2ecc71; color: white; padding: 5px 10px; border-radius: 4px; font-size: 14px; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p>Welcome! Your native PHP Model-View-Controller custom framework is fully functional.</p>
        <div class="badge">System Status: <?= htmlspecialchars($status) ?></div>
    </div>
</body>
</html>