<?php
?>
<!DOCTYPE html>
<html>
 <head>
  <title>Shutdown</title>
  <script type="text/javascript">
    function return_home() { document.location="/"; };
    setTimeout(return_home, 20000);
  </script>
 </head>
 <body>
  <h2>System Shutting Down</h2>
  <?php
    if (!(false === exec("sudo /usr/sbin/poweroff 2>&1", $results, $rc)) && ($rc == 0)) {
        echo "Shutdown initiated";
        echo "<script type=\"text/javascript\"> setTimeout(return_home, 7000);</script>";
    } 
    else {
        echo "Shutdown command failed -- $rc<br>";
        foreach($results as $num => $line) echo "<br>$line";
	echo "<p><br></p>";
	echo "<p><a href=\"/\">Home</a></p>";
    } 
  ?>
 </body>
</html>
