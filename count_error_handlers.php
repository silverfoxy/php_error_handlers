<?php

require 'vendor/autoload.php';
require 'ErrorHandlerVisitor.php';

use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

if (count($argv) < 2) {
    die('Usage: php count_error_handlers.php path/to/web/app'.PHP_EOL);
}

function getDirContents($dir, &$results = array()) {
    $files = scandir($dir);
    foreach($files as $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if (!$path) {
            // Directory not found or permission denied
            continue;
        }
        if(!is_dir($path)) {
            $results[] = $path;
        } else if($value != "." && $value != "..") {
            getDirContents($path, $results);
        }
    }

    return $results;
}

$target_dir = $argv[1];
echo '[+] Listing files in the target directory...'.PHP_EOL;
$files = getDirContents($target_dir);

$error_handler_func_count = 0;
$error_handler_method_count = 0;
$error_handler_class_count = 0;

$progressBar = new \ProgressBar\Manager(0, count($files));
$i = 0;

echo '[+] Analyzing the source code ...'.PHP_EOL;

foreach ($files as $key => $file_name) {
    if (array_key_exists('extension', pathinfo($file_name)) && in_array(pathinfo($file_name)['extension'], ['php', 'inc'])) {
        $code = file_get_contents($file_name);
        $parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP5);
        try {
            $ast = $parser->parse($code);
            $traverser = new PhpParser\NodeTraverser;
            $visitor = new ErrorHandlerVisitor();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);
            $visitor->calculateClassCount();

            $error_handler_func_count += $visitor->error_handler_func_count;
            $error_handler_method_count += $visitor->error_handler_method_count;
            $error_handler_class_count += $visitor->error_handler_class_count;
        } catch (PhpParser\Error $error) {
            if (substr($code, 0, 4) === '<?hh') {
                echo "[?] Skipping HHVM file {$file_name}" . PHP_EOL;
            }
            else {
                echo "[-] Parse error at {$file_name}: {$error->getMessage()}" . PHP_EOL;
            }
        }
    }
    $i++;
    $progressBar->update($i);
}
echo "[+] Producing results:".PHP_EOL;
echo "[+] Error Handler Functions: {$error_handler_func_count}".PHP_EOL;
echo "[+] Error Handler Methods: {$error_handler_method_count}".PHP_EOL;
echo "[+] Error Handler Classes: {$error_handler_class_count}".PHP_EOL;