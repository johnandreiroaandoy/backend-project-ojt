<?php
// namespace: Sets up the structural folder space path declaring that this file belongs to the core foundation of your framework.
namespace Core;

// class: A blueprint class container. This base class provides shared tools that other controllers will inherit.
class Controller {
    
    // protected function: A specialized method access rule. It means *only* this class and any class that inherits it (its children) can run this function.
    // string $viewName: Commands the function to expect the textual filename of the view you want to open (e.g., "home" or "contact").
    // array $data = []: Allows the controller to pass a optional list of data values (like variables or database lists) over to the view screen.
    protected function view(string $viewName, array $data = []) {
        
        // extract(): A powerful built-in PHP utility that takes an associative array and programmatically converts the keys into real, independent variables.
        // For example, if $data contains ['title' => 'Home'], extract() turns it into a standard variable: $title = 'Home'; so the HTML view can read it easily.
        extract($data);
        
        // __DIR__: A magic directory tracking constant that pinpoints exactly where this Core folder file is saved on the hard drive.
        // It builds an absolute string route leading straight into the "app/Views/" directory folder to look for your target file layout.
        $viewFile = __DIR__ . "/../app/Views/" . $viewName . ".php";
        
        // if (file_exists()): A safety check that inspects the server file system to verify if the requested HTML/PHP layout file actually exists.
        if (file_exists($viewFile)) {
            // require_once: Pulls in, executes, and renders the contents of the HTML/PHP template file directly onto the screen.
            require_once $viewFile;
        } else {
            // die(): Instantly stops all application processing and prints out a clear error message warning the developer that they forgot to build that specific view file.
            die("View file [{$viewName}] does not exist.");
        }
    }
}