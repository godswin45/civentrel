<?php
$dirs = [
    'd:/phpintalls/htdocs/civentrel/pages/usermanagement/*.php',
    'd:/phpintalls/htdocs/civentrel/pages/rolespermission/*.php',
    'd:/phpintalls/htdocs/civentrel/pages/citizen/*.php'
];

foreach ($dirs as $pattern) {
    foreach (glob($pattern) as $f) {
        $c = file_get_contents($f);
        // Only target pages that include header but don't have the auth check
        if (strpos($c, 'empty($_SESSION[\'user_id\'])') === false && strpos($c, 'header.php') !== false) {
            $pos = strpos($c, "<?php");
            if ($pos !== false) {
                $insert = "\nrequire_once __DIR__ . '/../../src/bootstrap.php';\nif (empty(\$_SESSION['user_id']) && empty(\$_SESSION['employee_id'])) { header('Location: ../../login.php'); exit; }\n";
                
                // Replace the first <?php
                $c = substr_replace($c, "<?php" . $insert, $pos, 5);
                
                // Remove any pre-existing bootstrap require that we just duplicated
                $c = preg_replace("/(require_once __DIR__ . '\/\.\.\/\.\.\/src\/bootstrap\.php';\s*)+/", "require_once __DIR__ . '/../../src/bootstrap.php';\n", $c);
                
                file_put_contents($f, $c);
                echo "Fixed: $f\n";
            }
        }
    }
}
echo "Done.\n";
