<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    
    <title><?= htmlspecialchars(isset($title) ? $title : 'Home') ?></title>
    
    <style>
        /* body: Styles the entire web page backdrop, setting clean fonts, spacing margins, and a subtle gray background color (#f4f6f9). */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 50px; background: #f4f6f9; color: #333; }
        
        /* .card: Designs a centered white box container with rounded corners and a smooth drop-shadow to make content stand out visually. */
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        
        /* h1: Styles the primary main heading, coloring it dark slate (#2c3e50) and removing unnecessary top spacing. */
        h1 { color: #2c3e50; margin-top: 0; }
        
        /* .badge: Creates a small, professional green (#2ecc71) alert box layout to emphasize status indicators cleanly. */
        .badge { background: #2ecc71; color: white; padding: 5px 10px; border-radius: 4px; font-size: 14px; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        
        <h1><?= htmlspecialchars(isset($title) ? $title : 'Home') ?></h1>
        
        <p>Welcome! Your native PHP Model-View-Controller custom framework is fully functional.</p>
        
        <div class="badge">System Status: <?= htmlspecialchars(isset($status) ? $status : 'Unknown') ?></div>
        
    </div> </body>
</html> ```

---

### 🎓 How to explain this to your Defense Panel

If your panelists look at this and ask: *"What role does this file play in your MVC framework, and how is it kept secure?"*, here is how your team answers:

> *"This file serves strictly as a **View** component within our custom MVC ecosystem. Following the design patterns of our framework, it contains zero database connections, zero processing workflows, and zero business logic. Instead, it relies on template variable injection (`$title` and `$status`) passed down by our controller. 
> 
> To ensure complete frontend safety, every dynamic string echo is wrapped inside **`htmlspecialchars()`**. This functions as our visual boundary defense, automatically scrubbing data characters into harmless HTML entities. This makes it impossible for malicious markup or persistent script components to hijack the client-side user interface, satisfying industry standard **Cross-Site Scripting (XSS)** mitigation policies."*