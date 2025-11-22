<?php
?>
<!DOCTYPE html>
<html>
 <head>
  <title>Shutdown</title>
 </head>
 <body>
  <h2>System Shutting Down</h2>
  <?php
    if (!(false === exec("sudo /usr/sbin/poweroff", $results, $rc)) && ($rc == 0)) {
        echo "Shutdown initiated";
    } 
    else {
        echo "Shutdown command failed";
        foreach($results as $num => $line) echo "$line<br>";
    } 
  ?>
 </body>
</html>
