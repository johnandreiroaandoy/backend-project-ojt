<?php
namespace Core;

class Controller {
    protected function view(string $viewName, array $data = []) {
        extract($data);
        $viewFile = __DIR__ . "/../app/Views/" . $viewName . ".php";
        
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die("View file [{$viewName}] does not exist.");
        }
    }
}