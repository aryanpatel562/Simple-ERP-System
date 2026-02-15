<?php
session_start();

// Get error message if exists
$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Page</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
  <link rel="stylesheet" href="login.css">
</head>

<body class="bg-light p-6">
    <div class="container mt-5">
      <div class="card shadow p-4">
       <h1 class="mb-4 text-center">Group 24's Website</h1>
     </div>
    </div>

    <div class="container py-4">
     <div class="row g-3 text-center">
        <div class="col-6 col-md-2">
         <img src="Audrey.JPG" class="img-fluid rounded shadow-sm" alt="Profile 1">
         <div class="card"><p>Audrey</p></div>
        </div>
       <div class="col-6 col-md-2">
         <img src="Julia.jpg" class="img-fluid rounded shadow-sm" alt="Profile 2">
         <div class="card"><p>Julia</p></div>
        </div>
       <div class="col-6 col-md-2">
         <img src="Pradyumn.jpeg" class="img-fluid rounded shadow-sm" alt="Profile 3">
         <div class="card"><p>Pradyumn</p></div>
       </div>
        <div class="col-6 col-md-2">
          <img src="Lauren.jpg" class="img-fluid rounded shadow-sm" alt="Profile 4">
         <div class="card"><p>Lauren</p></div>
       </div>
       <div class="col-6 col-md-2">
         <img src="Aryan.jpg" class="img-fluid rounded shadow-sm" alt="Profile 5">
          <div class="card"><p>Aryan</p></div>
        </div>
        <div class="col-6 col-md-2">
         <img src="Dylan.jpeg" class="img-fluid rounded shadow-sm" alt="Profile 6">
         <div class="card"><p>Dylan</p></div>
        </div>
     </div>
   </div>

    <div class="container mt-5">
      <div class="card shadow p-4">
        <h2 class="mb-4 text-center">Login</h2>

        <?php if (!empty($error_message)): ?>
       <div class="error-message">
          <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
       <?php endif; ?>

        <form action="login.php" method="post" name="myForm" onsubmit="return validate()">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username">
         </div>

         <div class="mb-3">
           <label class="form-label">Password</label>
           <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password">
         </div>

          <div class="d-flex justify-content-between">
           <input type="reset" class="btn btn-secondary" value="Reset">
           <input type="submit" class="btn btn-primary" value="Submit">
          </div>
        </form>
      </div>
    </div>
<script src="login.js"></script>
</body>
</html>
